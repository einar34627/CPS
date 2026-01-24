<?php

session_start();
require_once '../config/db_connection.php';

function cps_msg_key() {
    $dbn = isset($GLOBALS['dbname']) ? (string)$GLOBALS['dbname'] : 'cps';
    $usr = isset($GLOBALS['username']) ? (string)$GLOBALS['username'] : 'user';
    return hash('sha256', $dbn . '|' . $usr, true);
}
function cps_encrypt_text($plain) {
    $key = cps_msg_key();
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return [base64_encode($cipher ?: ''), base64_encode($iv)];
}
function cps_decrypt_text($b64, $ivb64) {
    $key = cps_msg_key();
    $cipher = base64_decode($b64, true);
    $iv = base64_decode($ivb64, true);
    if ($cipher === false || $iv === false) { return ''; }
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain !== false ? $plain : '';
}
function cps_messages_ensure_columns($pdo){
    try { $chk = $pdo->query("SHOW COLUMNS FROM messages LIKE 'enc_message'"); $ex = $chk && $chk->fetch(PDO::FETCH_ASSOC); if (!$ex) { $pdo->exec("ALTER TABLE messages ADD COLUMN enc_message TEXT DEFAULT NULL"); } } catch (Exception $e) {}
    try { $chk = $pdo->query("SHOW COLUMNS FROM messages LIKE 'iv'"); $ex = $chk && $chk->fetch(PDO::FETCH_ASSOC); if (!$ex) { $pdo->exec("ALTER TABLE messages ADD COLUMN iv VARCHAR(64) DEFAULT NULL"); } } catch (Exception $e) {}
    try { $chk = $pdo->query("SHOW COLUMNS FROM messages LIKE 'algo'"); $ex = $chk && $chk->fetch(PDO::FETCH_ASSOC); if (!$ex) { $pdo->exec("ALTER TABLE messages ADD COLUMN algo VARCHAR(32) DEFAULT 'AES-256-CBC'"); } } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    try {
        $uid = $_SESSION['user_id'] ?? 0;
        if (!$uid) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
        $action = $_POST['action'];
        if ($action === 'send_message') {
            $role = strtoupper(trim($_POST['recipient_role'] ?? 'TANOD'));
            if (!in_array($role, ['TANOD','SECRETARY','CAPTAIN'])) $role = 'TANOD';
            $msg = trim($_POST['message'] ?? '');
            if ($msg === '') { echo json_encode(['success'=>false,'error'=>'Message is empty']); exit(); }
            $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                recipient_role ENUM('TANOD','SECRETARY','CAPTAIN') NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            cps_messages_ensure_columns($pdo);
            [$cipher, $iv] = cps_encrypt_text($msg);
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, recipient_role, message, enc_message, iv, algo) VALUES (?, ?, ?, ?, ?, 'AES-256-CBC')");
            $stmt->execute([$uid, $role, '', $cipher, $iv]);
            echo json_encode(['success'=>true]);
            exit();
        } elseif ($action === 'list_messages') {
            $role = strtoupper(trim($_POST['recipient_role'] ?? 'TANOD'));
            if (!in_array($role, ['TANOD','SECRETARY','CAPTAIN'])) $role = 'TANOD';
            $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                recipient_role ENUM('TANOD','SECRETARY','CAPTAIN') NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            cps_messages_ensure_columns($pdo);
            $stmt = $pdo->prepare("SELECT id, sender_id, recipient_role, enc_message, iv, message, created_at FROM messages WHERE recipient_role = ? ORDER BY created_at DESC, id DESC LIMIT 200");
            $stmt->execute([$role]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $out = [];
            foreach ($rows as $r) {
                $text = cps_decrypt_text($r['enc_message'] ?? '', $r['iv'] ?? '');
                if ($text === '' && !empty($r['message'])) { $text = (string)$r['message']; }
                $out[] = [
                    'id' => $r['id'],
                    'sender_id' => $r['sender_id'],
                    'recipient_role' => $r['recipient_role'],
                    'message' => $text,
                    'created_at' => $r['created_at']
                ];
            }
            echo json_encode(['success'=>true,'messages'=>$out]);
            exit();
        } elseif ($action === 'user_list') {
            try {
                $stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, username, email, role, is_verified, created_at FROM users ORDER BY created_at DESC LIMIT 500");
                $stmt->execute([]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success'=>true,'users'=>$rows]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to load users']);
                exit();
            }
        } elseif ($action === 'user_create') {
            $first = trim($_POST['first_name'] ?? '');
            $middle = trim($_POST['middle_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $contact = trim($_POST['contact'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $dob = trim($_POST['date_of_birth'] ?? '');
            $role = strtoupper(trim($_POST['role'] ?? 'TANOD'));
            if (!in_array($role, ['TANOD','SECRETARY','CAPTAIN'])) $role = 'TANOD';
            if ($first === '' || $last === '' || $username === '' || $email === '' || $password === '' || $contact === '' || $address === '' || $dob === '') {
                echo json_encode(['success'=>false,'error'=>'Missing required fields']);
                exit();
            }
            try {
                $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
                $dup->execute([$email, $username]);
                if ((int)$dup->fetchColumn() > 0) { echo json_encode(['success'=>false,'error'=>'Email or username already exists']); exit(); }
                $hash = password_hash($password, PASSWORD_DEFAULT, ['cost'=>12]);
                $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, username, contact, address, date_of_birth, email, password, role, is_verified, verification_code, code_expiry) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$first, $middle, $last, $username, $contact, $address, $dob, $email, $hash, $role, 1, null, null]);
                echo json_encode(['success'=>true]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to create user']);
                exit();
            }
        } elseif ($action === 'profile_get') {
            try {
                $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, username, email, contact, address, date_of_birth, role, avatar_url, created_at, updated_at FROM users WHERE id = ?");
                $stmt->execute([$uid]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success'=>true,'profile'=>$row ?: []]);
                exit();
            } catch (Exception $e) {
                try {
                    $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, username, email, contact, address, date_of_birth, role, created_at, updated_at FROM users WHERE id = ?");
                    $stmt->execute([$uid]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $row = $row ?: [];
                    $row['avatar_url'] = null;
                    echo json_encode(['success'=>true,'profile'=>$row]);
                    exit();
                } catch (Exception $e2) {
                    echo json_encode(['success'=>false,'error'=>'Failed to load profile']);
                    exit();
                }
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
            $ext = 'jpg';
            if ($type === 'image/png') $ext = 'png';
            if ($type === 'image/webp') $ext = 'webp';
            $root = dirname(__DIR__);
            $dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $name = 'u'.$uid.'_'.bin2hex(random_bytes(6)).'.'.$ext;
            $dest = $dir . DIRECTORY_SEPARATOR . $name;
            if (!move_uploaded_file($file['tmp_name'], $dest)) { echo json_encode(['success'=>false,'error'=>'Failed to save image']); exit(); }
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) DEFAULT NULL");
            } catch (Exception $e) {}
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
                echo json_encode(['success'=>false,'error'=>'Failed to generate key']);
                exit();
            }
        } elseif ($action === 'security_toggle_2fa') {
            $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1' ? 1 : 0;
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
        } elseif ($action === 'complaint_set_status') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $status = strtolower(trim($_POST['status'] ?? 'resolved'));
            if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid ID']); exit(); }
            $mapped = ($status === 'resolved' || $status === 'closed') ? 'resolved' : 'pending';
            try {
                $stmt = $pdo->prepare("UPDATE complaints SET status = ? WHERE id = ?");
                $stmt->execute([$mapped, $id]);
                echo json_encode(['success'=>true,'status'=>$mapped]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to update status']);
                exit();
            }
        } elseif ($action === 'volunteer_set_status') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $status = strtolower(trim($_POST['status'] ?? 'accepted'));
            if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid ID']); exit(); }
            $allowed = ['accepted','declined','pending'];
            if (!in_array($status, $allowed, true)) { $status = 'accepted'; }
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS volunteers (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, created_at DATETIME NOT NULL, status VARCHAR(20) DEFAULT 'pending')");
            } catch (Exception $e) {}
            try {
                $stmt = $pdo->prepare("UPDATE volunteers SET status = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                echo json_encode(['success'=>true,'status'=>$status]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to update volunteer status']);
                exit();
            }
        } elseif ($action === 'event_registration_set_status') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $status = strtolower(trim($_POST['status'] ?? 'accepted'));
            if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid ID']); exit(); }
            $allowed = ['accepted','declined','pending'];
            if (!in_array($status, $allowed, true)) { $status = 'accepted'; }
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS event_registrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    name VARCHAR(255) DEFAULT NULL,
                    address VARCHAR(255) DEFAULT NULL,
                    contact VARCHAR(50) DEFAULT NULL,
                    email VARCHAR(255) DEFAULT NULL,
                    type VARCHAR(100) DEFAULT NULL,
                    skills TEXT DEFAULT NULL,
                    volunteer TINYINT(1) DEFAULT 0,
                    status VARCHAR(20) DEFAULT 'pending',
                    created_at DATETIME NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Exception $e) {}
            try {
                $stmt = $pdo->prepare("UPDATE event_registrations SET status = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                echo json_encode(['success'=>true,'status'=>$status]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to update event registration status']);
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

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}


$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, middle_name, last_name, role, avatar_url, email, username, contact, address, date_of_birth FROM users WHERE id = ?";
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (Exception $e) {
    $fallbackQuery = "SELECT first_name, middle_name, last_name, role, email, username, contact, address, date_of_birth FROM users WHERE id = ?";
    $stmt = $pdo->prepare($fallbackQuery);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (is_array($user)) { $user['avatar_url'] = null; }
}

if ($user) {
    $first_name = htmlspecialchars($user['first_name']);
    $middle_name = htmlspecialchars($user['middle_name']);
    $last_name = htmlspecialchars($user['last_name']);
    $role = htmlspecialchars($user['role']);
    $avatar_url = isset($user['avatar_url']) ? $user['avatar_url'] : null;
    $avatar_path = $avatar_url ? '../'.$avatar_url : 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
    $username = htmlspecialchars($user['username'] ?? '');
    $contact = htmlspecialchars($user['contact'] ?? '');
    $address = htmlspecialchars($user['address'] ?? '');
    $date_of_birth = htmlspecialchars($user['date_of_birth'] ?? '');
    $email = htmlspecialchars($user['email'] ?? '');
    
    $full_name = $first_name;
    if (!empty($middle_name)) {
        $full_name .= " " . $middle_name;
    }
    $full_name .= " " . $last_name;
} else {

    $full_name = "User";
    $role = "USER";
    $avatar_path = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR4nGNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
}

$stmt = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Policing and Surveillance</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="../css/dashboard.css">
    <?php
    try {
        $colExists = false;
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'watch_group_member'");
            $colExists = $chk && $chk->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e0) {}
        if ($colExists) {
            $watch_stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, email, role, is_verified FROM users WHERE watch_group_member = 1 OR role LIKE ?");
            $watch_stmt->execute(['%WATCH%']);
        } else {
            $watch_stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, email, role, is_verified FROM users WHERE role LIKE ?");
            $watch_stmt->execute(['%WATCH%']);
        }
        $watch_members = $watch_stmt->fetchAll(PDO::FETCH_ASSOC);
        $watch_stmt = null;
    } catch (Exception $e) {
        $watch_members = [];
    }

    $observations = [];
    try {
        $obs_stmt = $pdo->prepare("SELECT id, observed_at, location, category, description, status FROM watch_observations ORDER BY observed_at DESC LIMIT 100");
        $obs_stmt->execute([]);
        $observations = $obs_stmt->fetchAll(PDO::FETCH_ASSOC);
        $obs_stmt = null;
    } catch (Exception $e) {
        try {
            $obs_stmt = $pdo->prepare("SELECT id, observed_at, location, category, description, status FROM observations ORDER BY observed_at DESC LIMIT 100");
            $obs_stmt->execute([]);
            $observations = $obs_stmt->fetchAll(PDO::FETCH_ASSOC);
            $obs_stmt = null;
        } catch (Exception $e2) {
            $observations = [];
        }
    }

    $patrol_assignments = [];
    try {
        $pa_stmt = $pdo->prepare("SELECT user_id, zone, street, assigned_at FROM watch_patrol_assignments ORDER BY assigned_at DESC");
        $pa_stmt->execute([]);
        $patrol_assignments = $pa_stmt->fetchAll(PDO::FETCH_ASSOC);
        $pa_stmt = null;
    } catch (Exception $e) {
        try {
            $pa_stmt = $pdo->prepare("SELECT user_id, zone, street, assigned_at FROM patrol_assignments ORDER BY assigned_at DESC");
            $pa_stmt->execute([]);
            $patrol_assignments = $pa_stmt->fetchAll(PDO::FETCH_ASSOC);
            $pa_stmt = null;
        } catch (Exception $e2) {
            $patrol_assignments = [];
        }
    }

    $cameras = [
        ['id'=>1,'name'=>'Entrance Cam','location'=>'Main Gate','stream_url'=>'https://example.com/cam1'],
        ['id'=>2,'name'=>'Lobby Cam','location'=>'Municipal Hall Lobby','stream_url'=>'https://example.com/cam2'],
        ['id'=>3,'name'=>'Parking Cam','location'=>'North Parking','stream_url'=>'https://example.com/cam3'],
    ];

    $evidence_archive = [
        ['id'=>101,'title'=>'Gate Incident 2025-11-21','camera'=>'Entrance Cam','recorded_at'=>'2025-11-21 14:10','size'=>'24 MB','url'=>'../evidence/gate_incident_20251121.mp4'],
        ['id'=>102,'title'=>'Lobby Disturbance 2025-11-23','camera'=>'Lobby Cam','recorded_at'=>'2025-11-23 09:35','size'=>'12 MB','url'=>'../evidence/lobby_disturbance_20251123.mp4'],
        ['id'=>103,'title'=>'Parking Lot Theft 2025-11-25','camera'=>'Parking Cam','recorded_at'=>'2025-11-25 21:05','size'=>'86 MB','url'=>'../evidence/parking_theft_20251125.mp4'],
    ];

    $complaints = [];
    try {
        $stmtC = $pdo->prepare("SELECT id, resident, issue, category, location, submitted_at, status, anonymous FROM complaints ORDER BY submitted_at DESC LIMIT 500");
        $stmtC->execute([]);
        $complaints = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        $stmtC = null;
    } catch (Exception $e) {
        $complaints = [];
    }

    $complaint_analytics = [];
    foreach ($complaints as $c) {
        $cat = isset($c['category']) && $c['category'] !== '' ? $c['category'] : 'General';
        $loc = isset($c['location']) && $c['location'] !== '' ? $c['location'] : '—';
        $k = $cat.'|'.$loc;
        if (!isset($complaint_analytics[$k])) {
            $concern = $cat.' • '.$loc;
            $complaint_analytics[$k] = ['cat'=>$cat,'loc'=>$loc,'concern'=>$concern,'reports'=>0,'pending'=>0,'resolved'=>0,'last'=>''];
        }
        $complaint_analytics[$k]['reports']++;
        $st = strtolower(trim($c['status'] ?? ''));
        if ($st === 'resolved' || $st === 'closed') { $complaint_analytics[$k]['resolved']++; } else { $complaint_analytics[$k]['pending']++; }
        $sa = $c['submitted_at'] ?? '';
        if ($sa && (!$complaint_analytics[$k]['last'] || strtotime($sa) > strtotime($complaint_analytics[$k]['last']))) { $complaint_analytics[$k]['last'] = $sa; }
    }
    $complaint_analytics_rows = array_values($complaint_analytics);

    $volunteers = [];
    try {
        $stmtV = $pdo->prepare("SELECT v.id, v.user_id, v.preferred_zone, v.availability, v.preferred_days, v.time_slots, v.night_duty, v.max_hours, v.role_prefs, v.skills, v.previous_volunteer, v.prev_org, v.years_experience, v.physical_fit, v.medical_conditions, v.long_period, v.valid_id_url, v.status, v.created_at, u.first_name, u.middle_name, u.last_name, u.contact, u.email FROM volunteers v LEFT JOIN users u ON v.user_id = u.id ORDER BY v.created_at DESC LIMIT 500");
        $stmtV->execute([]);
        $rowsV = $stmtV->fetchAll(PDO::FETCH_ASSOC);
        $stmtV = null;
        foreach ($rowsV as $row) {
            $name = trim(($row['first_name'] ?? '').' '.($row['middle_name'] ?? '').' '.($row['last_name'] ?? ''));
            $volunteers[] = [
                'id' => (int)($row['id'] ?? 0),
                'name' => $name !== '' ? $name : '—',
                'role' => 'Volunteer',
                'contact' => $row['contact'] ?? '',
                'email' => $row['email'] ?? '',
                'zone' => $row['preferred_zone'] ?? '',
                'availability' => $row['availability'] ?? '',
                'preferred_days' => $row['preferred_days'] ?? '',
                'time_slots' => $row['time_slots'] ?? '',
                'night_duty' => isset($row['night_duty']) ? (int)$row['night_duty'] : 0,
                'max_hours' => isset($row['max_hours']) ? (int)$row['max_hours'] : null,
                'role_prefs' => $row['role_prefs'] ?? '',
                'skills' => $row['skills'] ?? '',
                'previous_volunteer' => isset($row['previous_volunteer']) ? (int)$row['previous_volunteer'] : 0,
                'prev_org' => $row['prev_org'] ?? '',
                'years_experience' => isset($row['years_experience']) ? (int)$row['years_experience'] : null,
                'physical_fit' => isset($row['physical_fit']) ? (int)$row['physical_fit'] : null,
                'medical_conditions' => $row['medical_conditions'] ?? '',
                'long_period' => isset($row['long_period']) ? (int)$row['long_period'] : null,
                'valid_id_url' => $row['valid_id_url'] ?? '',
                'status' => $row['status'] ?? '',
                'created_at' => $row['created_at'] ?? ''
            ];
        }
    } catch (Exception $e) {
        $volunteers = [];
    }
    ?>
    <style>
        .registry-card { padding: 24px; border-radius: 16px; background: var(--card-bg,#fff); box-shadow: var(--card-shadow,0 10px 25px rgba(0,0,0,0.05)); }
        .registry-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .registry-title { font-size:20px; font-weight:600; }
        .registry-table { width:100%; border-collapse: collapse; }
        .registry-table th, .registry-table td { padding:12px 16px; border-bottom: 1px solid rgba(0,0,0,0.08); text-align:left; }
        .registry-table th { font-weight:600; color: var(--text-600,#555); background: var(--table-head-bg,transparent); }
        .registry-row { cursor:pointer; }
        .registry-row:hover { background: rgba(0,0,0,0.03); }
        .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }
        .badge-role { background:#f0f3ff; color:#3f51b5; }
        .badge-active { background:#e6f9ed; color:#15803d; }
        .badge-inactive { background:#f3f4f6; color:#6b7280; }
        .badge-pending { background:#fff7ed; color:#c2410c; }
        .badge-resolved { background:#ecfdf5; color:#047857; }
        .content-section { display:none; }
        #home-section { display:block; }
        .details-panel { margin-top:16px; padding:16px; border-radius:12px; background: rgba(0,0,0,0.03); }
        .log-card { padding:24px; border-radius:16px; background: var(--card-bg,#fff); box-shadow: var(--card-shadow,0 10px 25px rgba(0,0,0,0.05)); }
        .log-table { width:100%; border-collapse: collapse; }
        .log-table th, .log-table td { padding:12px 16px; border-bottom: 1px solid rgba(0,0,0,0.08); text-align:left; }
        .log-table th { font-weight:600; color: var(--text-600,#555); }
        .assign-card { padding:24px; border-radius:16px; background: var(--card-bg,#fff); box-shadow: var(--card-shadow,0 10px 25px rgba(0,0,0,0.05)); }
        .assign-table { width:100%; border-collapse: collapse; }
        .assign-table th, .assign-table td { padding:12px 16px; border-bottom: 1px solid rgba(0,0,0,0.08); text-align:left; }
        .assign-table th { font-weight:600; color: var(--text-600,#555); }
        .input-text { width:100%; padding:8px 10px; border:1px solid rgba(0,0,0,0.1); border-radius:8px; background: var(--input-bg,#fff); }
        .assign-controls { display:flex; gap:8px; align-items:center; }
        .settings-dropdown-container { position: relative; }
        .settings-dropdown-menu { position: absolute; right: 0; top: calc(100% + 8px); min-width: 160px; background: var(--glass-bg); border: 1px solid var(--glass-border); box-shadow: var(--glass-shadow); border-radius: 8px; display: none; padding: 8px; z-index: 1000; }
        .settings-dropdown-menu.active { display: block; }
        .settings-dropdown-item { width: 100%; padding: 10px 12px; border: none; background: rgba(255,255,255,0.1); color: var(--text-color); text-align: left; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; }
        .settings-dropdown-item:hover { background-color: rgba(255,255,255,0.2); }
        .dark-mode .settings-dropdown-item:hover { background-color: rgba(255,255,255,0.1); }
        .settings-section { padding: 24px; }
        .settings-card { padding: 24px; border-radius: 16px; background: var(--card-bg,#fff); box-shadow: var(--card-shadow,0 10px 25px rgba(0,0,0,0.05)); }
        .settings-nav { display:flex; gap:12px; border-bottom:1px solid var(--border-color); margin-bottom:16px; }
        .settings-tab { padding:8px 16px; border-radius:8px; cursor:pointer; border:1px solid var(--border-color); background: rgba(255,255,255,0.2); color: var(--text-color); }
        .settings-tab.active { background: var(--icon-bg-green); color: var(--icon-green); border-color: transparent; }
        .settings-title { font-size:20px; font-weight:600; margin-bottom: 12px; }
        .settings-list { display:flex; flex-direction:column; gap:12px; }
        .settings-item { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; border-radius:12px; background: rgba(255,255,255,0.2); border:1px solid var(--border-color); }
        .settings-item-left { display:flex; align-items:center; gap:12px; }
        .settings-item-icon { width:36px; height:36px; border-radius:999px; display:flex; align-items:center; justify-content:center; background: rgba(255,255,255,0.25); }
        .settings-item-title { font-weight:600; }
        .settings-item-desc { font-size:12px; color: var(--text-light); }
        .settings-danger { margin-top:16px; padding:16px; border-radius:12px; background: #fee2e2; color:#b91c1c; border:1px solid #fecaca; }
        .settings-danger-title { font-weight:600; margin-bottom:8px; display:flex; align-items:center; gap:8px; }
    </style>
</head>
<body>
    <div class="dashboard-animation" id="dashboard-animation">
        <div class="animation-logo">
            <div class="animation-logo-icon">
                <img src="../img/cpas-logo.png" alt="Community Policing and Surveillance Logo" style="width: 70px; height: 75px;">
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
                    <img src="../img/cpas-logo.png" alt="Community Policing and Surveillance Logo" style="width: 40px; height: 45px;">
                </div>
                <span class="logo-text">Community Policing and Surveillance</span>
            </div>
            
          <!-- Menu Section -->
<div class="menu-section">
    <p class="menu-title">COMMUNITY POLICING AND SURVEILLANCE</p>
    
    <div class="menu-items">
        <a href="#" class="menu-item active" id="dashboard-menu">
            <div class="icon-box icon-bg-red">
                <i class='bx bxs-dashboard icon-red'></i>
            </div>
            <span class="font-medium">Dashboard</span>
        </a>
        
        <div class="menu-item" onclick="toggleSubmenu('fire-incident')">
            <div class="icon-box icon-bg-orange">
                <i class='bx bxs-alarm-exclamation icon-orange'></i>
            </div>
            <span class="font-medium">Barangay Watch Coordination</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="fire-incident" class="submenu">
            <a href="#" class="submenu-item">Member Registry</a>
            <a href="#" class="submenu-item">Observation Logging</a>
            <a href="#" class="submenu-item">Patrol Assignment</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('volunteer')">
            <div class="icon-box icon-bg-blue">
                <i class='bx bxs-user-detail icon-blue'></i>
            </div>
            <span class="font-medium">CCTV Monitoring Management</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="volunteer" class="submenu">
            <a href="#" class="submenu-item">Live Viewer</a>
            <a href="#" class="submenu-item">Evidence Archive</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('inventory')">
            <div class="icon-box icon-bg-green">
                <i class='bx bxs-cube icon-green'></i>
            </div>
            <span class="font-medium">Complaint Logging Management</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="inventory" class="submenu">
            <a href="#" class="submenu-item">Online Form</a>
            <a href="#" class="submenu-item">Status Tracker</a>
            <a href="#" class="submenu-item">Analytics</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('schedule')">
            <div class="icon-box icon-bg-purple">
                <i class='bx bxs-calendar icon-purple'></i>
            </div>
            <span class="font-medium">Volunteer and Tanod Availability Management</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="schedule" class="submenu">
            <a href="#" class="submenu-item">Volunteer Registry Database</a>
            <a href="#" class="submenu-item">Duty Roster</a>
            <a href="#" class="submenu-item">Attendance Logs</a>
            <a href="#" class="submenu-item">Task Assignment</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('training')">
            <div class="icon-box icon-bg-teal">
                <i class='bx bxs-graduation icon-teal'></i>
            </div>
            <span class="font-medium">Patrol Route and Activity Monitoring</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="training" class="submenu">
            <a href="#" class="submenu-item">Route Monitoring</a>
            <a href="#" class="submenu-item" id="gps-tracking-link" data-target="gps-tracking-section">GPS Tracking</a>
            <a href="#" class="submenu-item">Summary Reports</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('inspection')">
            <div class="icon-box icon-bg-yellow">
                <i class='bx bxs-check-shield icon-yellow'></i>
            </div>
            <span class="font-medium">Awareness and Event Tracking</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="inspection" class="submenu">
            <a href="#" class="submenu-item">Registration System</a>
            <a href="#" class="submenu-item">Event Scheduling</a>
            <a href="#" class="submenu-item">Feedback</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('postincident')">
            <div class="icon-box icon-bg-pink">
                <i class='bx bxs-file-doc icon-pink'></i>
            </div>
            <span class="font-medium">Anonymous Feedback and Tip Line</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="postincident" class="submenu">
            <a href="#" class="submenu-item">Tip Portal</a>
            <a href="#" class="submenu-item">Message Encryption</a>
        </div>
        <a href="#" class="menu-item" id="user-menu">
            <div class="icon-box icon-bg-purple">
                <i class='bx bxs-user icon-purple'></i>
            </div>
            <span class="font-medium">User</span>
        </a>
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
            </div>
            
            <div class="dashboard-content content-section" id="home-section">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Community & Surveillance Dashboard</h1>
                        <p class="dashboard-subtitle">Monitor, manage, and coordinate community & surveillance operations.</p>
                    </div>
                    <div class="dashboard-actions">
                        <a href="Summary%20Report.php" class="primary-button" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                            <i class='bx bxs-file-blank' style="font-size: 18px;"></i>
                            Summary Report
                        </a>
                        <button class="primary-button">
                            <span style="font-size: 20px;">+</span>
                            Generate Report
                        </button>
                        <button class="secondary-button">
                            System Backup
                        </button>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card stat-card-primary">
                        <div class="stat-header">
                            <span class="stat-title">Pending Approvals</span>
                            <button class="stat-button stat-button-primary">
                                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="stat-value">12</div>
                        <div class="stat-info">
                            <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span>5 new today</span>
                        </div>
                    </div>
                    
                    <div class="stat-card stat-card-white">
                        <div class="stat-header">
                            <span class="stat-title">Active Incidents</span>
                            <button class="stat-button stat-button-white">
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
                            <span>2 high priority</span>
                        </div>
                    </div>
                    
                    <div class="stat-card stat-card-white">
                        <div class="stat-header">
                            <span class="stat-title">System Users</span>
                            <button class="stat-button stat-button-white">
                                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="stat-value">156</div>
                        <div class="stat-info">
                            <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span>42 volunteers</span>
                        </div>
                    </div>
                    
                    <div class="stat-card stat-card-white">
                        <div class="stat-header">
                            <span class="stat-title">Uptime</span>
                            <button class="stat-button stat-button-white">
                                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="stat-value">99.8%</div>
                        <div class="stat-info">
                            <span>Last 30 days</span>
                        </div>
                    </div>
                </div>
                
                <!-- Main Grid -->
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">System Overview</h2>
                            <div class="response-chart">
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-red" style="height: 65%;"></div>
                                    <span class="chart-bar-label">Incidents</span>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-orange" style="height: 45%;"></div>
                                    <span class="chart-bar-label">Users</span>
                                </div>
                                <div class="chart-bar bar-highlight">
                                    <div class="chart-bar-value bar-yellow" style="height: 80%;"></div>
                                    <span class="chart-bar-label">Volunteers</span>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-green" style="height: 90%;"></div>
                                    <span class="chart-bar-label">Resources</span>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-blue" style="height: 55%;"></div>
                                    <span class="chart-bar-label">Training</span>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-purple" style="height: 70%;"></div>
                                    <span class="chart-bar-label">Inspections</span>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-pink" style="height: 35%;"></div>
                                    <span class="chart-bar-label">Reports</span>
                                </div>
                            </div>
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 85%;"></div>
                                </div>
                                <div class="progress-labels">
                                    <span>System Performance</span>
                                    <span>85% Optimal</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions & Pending Approvals -->
                        <div class="two-column-grid">
                            <div class="card">
                                <h2 class="card-title">Quick Actions</h2>
                                <div class="quick-actions">
                                    <div class="action-button">
                                        <div class="icon-box icon-bg-red">
                                            <i class='bx bxs-user-check icon-red'></i>
                                        </div>
                                        <span class="action-label">Approve Users</span>
                                    </div>
                                    <div class="action-button">
                                        <div class="icon-box icon-bg-blue">
                                            <i class='bx bxs-file-check icon-blue'></i>
                                        </div>
                                        <span class="action-label">Review Reports</span>
                                    </div>
                                    <div class="action-button">
                                        <div class="icon-box icon-bg-purple">
                                            <i class='bx bxs-cog icon-purple'></i>
                                        </div>
                                        <span class="action-label">System Config</span>
                                    </div>
                                    <div class="action-button">
                                        <div class="icon-box icon-bg-yellow">
                                            <i class='bx bxs-bar-chart-alt-2 icon-yellow'></i>
                                        </div>
                                        <span class="action-label">View Analytics</span>
                                    </div>
                                    <a href="Summary%20Report.php" class="action-button" style="text-decoration: none; color: inherit;">
                                        <div class="icon-box icon-bg-teal">
                                            <i class='bx bxs-file-blank icon-teal'></i>
                                        </div>
                                        <span class="action-label">Summary Report</span>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Pending Approvals -->
                            <div class="card">
                                <div class="incident-header">
                                    <h2 class="card-title">Pending Approvals</h2>
                                    <button class="secondary-button" style="font-size: 14px; padding: 8px 16px;">View All</button>
                                </div>
                                <div class="incident-list">
                                    <div class="incident-item">
                                        <div class="incident-icon icon-red">
                                            <i class='bx bxs-user-plus icon-red'></i>
                                        </div>
                                        <div class="incident-info">
                                            <p class="incident-name">New Volunteer Applications</p>
                                            <p class="incident-location">5 applications pending review</p>
                                        </div>
                                        <span class="status-badge status-pending">Review</span>
                                    </div>
                                    <div class="incident-item">
                                        <div class="incident-icon icon-yellow">
                                            <i class='bx bxs-report icon-yellow'></i>
                                        </div>
                                        <div class="incident-info">
                                            <p class="incident-name">Incident Reports</p>
                                            <p class="incident-location">3 reports awaiting validation</p>
                                        </div>
                                        <span class="status-badge status-progress">Validate</span>
                                    </div>
                                    <div class="incident-item">
                                        <div class="incident-icon icon-blue">
                                            <i class='bx bxs-cog icon-blue'></i>
                                        </div>
                                        <div class="incident-info">
                                            <p class="incident-name">Maintenance Requests</p>
                                            <p class="incident-location">2 equipment repairs pending</p>
                                        </div>
                                        <span class="status-badge status-completed">Approve</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                   
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">System Alerts</h2>
                            <div class="alert-card">
                                <h3 class="alert-title">System Backup Required</h3>
                                <p class="alert-time">Last backup: 2 days ago | Recommended: Daily</p>
                                <button class="alert-button">
                                    <svg class="button-icon" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4z"></path>
                                    </svg>
                                    Run Backup
                                </button>
                            </div>
                            <div class="alert-card">
                                <h3 class="alert-title">Certificate Expiry Notice</h3>
                                <p class="alert-time">5 training certificates expiring in 30 days</p>
                                <button class="alert-button">
                                    <svg class="button-icon" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4z"></path>
                                    </svg>
                                    View Details
                                </button>
                            </div>
                        </div>
                        
                        <!-- User Activity -->
                        <div class="card">
                            <div class="personnel-header">
                                <h2 class="card-title">Recent User Activity</h2>
                                <button class="secondary-button" style="font-size: 14px; padding: 8px 16px;">Refresh</button>
                            </div>
                            <div class="personnel-list">
                                <div class="personnel-item">
                                    <div class="personnel-icon icon-cyan">
                                        <i class='bx bxs-user icon-cyan'></i>
                                    </div>
                                    <div class="personnel-info">
                                        <p class="personnel-name">Admin User - System Config</p>
                                        <p class="personnel-details">Updated notification settings</p>
                                    </div>
                                </div>
                                <div class="personnel-item">
                                    <div class="personnel-icon icon-purple">
                                        <i class='bx bxs-user icon-purple'></i>
                                    </div>
                                    <div class="personnel-info">
                                        <p class="personnel-name">Staff Member - Incident Report</p>
                                        <p class="personnel-details">Submitted new incident report</p>
                                    </div>
                                </div>
                                <div class="personnel-item">
                                    <div class="personnel-icon icon-indigo">
                                        <i class='bx bxs-user-badge icon-indigo'></i>
                                    </div>
                                    <div class="personnel-info">
                                        <p class="personnel-name">Volunteer - Training</p>
                                        <p class="personnel-details">Completed safety training</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Status -->
                        <div class="card">
                            <h2 class="card-title">System Status</h2>
                            <div class="equipment-container">
                                <div class="equipment-circle">
                                    <svg class="equipment-svg">
                                        <circle cx="96" cy="96" r="80" class="equipment-background"></circle>
                                        <circle cx="96" cy="96" r="80" class="equipment-fill"></circle>
                                    </svg>
                                    <div class="equipment-text">
                                        <span class="equipment-value">99.8%</span>
                                        <span class="equipment-label">Uptime</span>
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
            <div class="content-section" id="member-registry-section">
                <div class="registry-card">
                    <div class="registry-header">
                        <div class="registry-title">Member Registry</div>
                        <button class="secondary-button" id="registry-back">Back to Dashboard</button>
                    </div>
                    <table class="registry-table" id="registry-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($watch_members as $m): ?>
                                <?php
                                    $name = htmlspecialchars(trim(($m['first_name'] ?? '').' '.($m['middle_name'] ?? '').' '.($m['last_name'] ?? '')));
                                    $email = htmlspecialchars($m['email'] ?? '');
                                    $roleLabel = htmlspecialchars($m['role'] ?? '');
                                    $statusLabel = (!empty($m['is_verified']) && (int)$m['is_verified'] === 1) ? 'Active' : 'Inactive';
                                ?>
                                <tr class="registry-row" data-id="<?php echo (int)$m['id']; ?>" data-name="<?php echo $name; ?>" data-email="<?php echo $email; ?>" data-role="<?php echo $roleLabel; ?>">
                                    <td><?php echo $name; ?></td>
                                    <td><?php echo $email; ?></td>
                                    <td><span class="badge badge-role"><?php echo $roleLabel; ?></span></td>
                                    <td>
                                        <?php if ($statusLabel === 'Active'): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($watch_members)): ?>
                                <tr>
                                    <td colspan="4">No watch group members found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="registry-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="observation-logging-section">
                <div class="log-card">
                    <div class="registry-header">
                        <div class="registry-title">Observation Logging</div>
                        <button class="secondary-button" id="obs-back">Back to Dashboard</button>
                    </div>
                    <table class="log-table" id="obs-table">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Location</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($observations as $o): ?>
                                <?php
                                    $dt = htmlspecialchars($o['observed_at'] ?? '');
                                    $loc = htmlspecialchars($o['location'] ?? '');
                                    $cat = htmlspecialchars($o['category'] ?? '');
                                    $descRaw = $o['description'] ?? '';
                                    $descSafe = htmlspecialchars($descRaw);
                                    $descShort = (strlen($descSafe) > 80) ? substr($descSafe,0,77).'...' : $descSafe;
                                    $status = strtolower(trim($o['status'] ?? ''));
                                    $statusLabel = $status === 'resolved' || $status === 'closed' ? 'Resolved' : 'Pending';
                                ?>
                                <tr class="obs-row" data-dt="<?php echo $dt; ?>" data-loc="<?php echo $loc; ?>" data-cat="<?php echo $cat; ?>" data-desc="<?php echo $descSafe; ?>" data-status="<?php echo $statusLabel; ?>">
                                    <td><?php echo $dt; ?></td>
                                    <td><?php echo $loc; ?></td>
                                    <td><?php echo $cat; ?></td>
                                    <td><?php echo $descShort; ?></td>
                                    <td>
                                        <?php if ($statusLabel === 'Resolved'): ?>
                                            <span class="badge badge-resolved">Resolved</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($observations)): ?>
                                <tr>
                                    <td colspan="5">No observations logged yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="obs-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="patrol-assignment-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Patrol Assignment</div>
                        <button class="secondary-button" id="assign-back">Back to Dashboard</button>
                    </div>
                    <table class="assign-table" id="assign-table">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Status</th>
                                <th>Zone</th>
                                <th>Street</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                $assignMap = [];
                                foreach ($patrol_assignments as $pa) {
                                    $uid = isset($pa['user_id']) ? (int)$pa['user_id'] : 0;
                                    if ($uid) $assignMap[$uid] = $pa;
                                }
                            ?>
                            <?php foreach ($watch_members as $m): ?>
                                <?php
                                    $name = htmlspecialchars(trim(($m['first_name'] ?? '').' '.($m['middle_name'] ?? '').' '.($m['last_name'] ?? '')));
                                    $statusLabel = (!empty($m['is_verified']) && (int)$m['is_verified'] === 1) ? 'Active' : 'Inactive';
                                    $zoneVal = '';
                                    $streetVal = '';
                                    $uid = (int)($m['id'] ?? 0);
                                    if ($uid && isset($assignMap[$uid])) {
                                        $zoneVal = htmlspecialchars($assignMap[$uid]['zone'] ?? '');
                                        $streetVal = htmlspecialchars($assignMap[$uid]['street'] ?? '');
                                    }
                                ?>
                                <tr class="assign-row" data-id="<?php echo $uid; ?>" data-name="<?php echo $name; ?>" data-status="<?php echo $statusLabel; ?>">
                                    <td><?php echo $name; ?></td>
                                    <td>
                                        <?php if ($statusLabel === 'Active'): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><input type="text" class="input-text zone-input" value="<?php echo $zoneVal; ?>" placeholder="Zone"></td>
                                    <td><input type="text" class="input-text street-input" value="<?php echo $streetVal; ?>" placeholder="Street"></td>
                                    <td class="assign-controls">
                                        <button class="primary-button assign-btn">Assign</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($watch_members)): ?>
                                <tr>
                                    <td colspan="5">No watch group members found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="assign-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="live-viewer-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">CCTV Live Viewer</div>
                        <button class="secondary-button" id="live-back">Back to Dashboard</button>
                    </div>
                    <table class="assign-table" id="cctv-table">
                        <thead>
                            <tr>
                                <th>Camera</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cameras as $cam): ?>
                                <?php
                                    $cid = (int)($cam['id'] ?? 0);
                                    $cname = htmlspecialchars($cam['name'] ?? '');
                                    $cloc = htmlspecialchars($cam['location'] ?? '');
                                    $curl = htmlspecialchars($cam['stream_url'] ?? '');
                                ?>
                                <tr class="cctv-row" data-id="<?php echo $cid; ?>" data-name="<?php echo $cname; ?>" data-location="<?php echo $cloc; ?>" data-url="<?php echo $curl; ?>">
                                    <td><?php echo $cname; ?></td>
                                    <td><?php echo $cloc; ?></td>
                                    <td><span class="badge badge-inactive">Offline</span></td>
                                    <td class="assign-controls">
                                        <button class="primary-button open-cam-btn">Open Camera</button>
                                        <button class="secondary-button connect-cam-btn">Connect External Camera</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($cameras)): ?>
                                <tr>
                                    <td colspan="4">No cameras configured.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="live-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="evidence-archive-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Evidence Archive</div>
                        <button class="secondary-button" id="evidence-back">Back to Dashboard</button>
                    </div>
                    <table class="assign-table" id="evidence-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Camera</th>
                                <th>Recorded At</th>
                                <th>Size</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($evidence_archive as $ev): ?>
                                <?php
                                    $eid = (int)($ev['id'] ?? 0);
                                    $title = htmlspecialchars($ev['title'] ?? '');
                                    $cam = htmlspecialchars($ev['camera'] ?? '');
                                    $at = htmlspecialchars($ev['recorded_at'] ?? '');
                                    $size = htmlspecialchars($ev['size'] ?? '');
                                    $url = htmlspecialchars($ev['url'] ?? '');
                                ?>
                                <tr class="evidence-row" data-id="<?php echo $eid; ?>" data-title="<?php echo $title; ?>" data-camera="<?php echo $cam; ?>" data-at="<?php echo $at; ?>" data-size="<?php echo $size; ?>" data-url="<?php echo $url; ?>">
                                    <td><?php echo $title; ?></td>
                                    <td><?php echo $cam; ?></td>
                                    <td><?php echo $at; ?></td>
                                    <td><?php echo $size; ?></td>
                                    <td class="assign-controls">
                                        <button class="primary-button evidence-open-btn">Open</button>
                                        <a class="secondary-button" href="<?php echo $url; ?>" download>Download</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($evidence_archive)): ?>
                                <tr>
                                    <td colspan="5">No recordings archived.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="evidence-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="complaint-online-form-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Residents Issues Log</div>
                        <button class="secondary-button" id="complaint-back">Back to Dashboard</button>
                    </div>
                    <table class="assign-table" id="complaint-table">
                        <thead>
                            <tr>
                                <th>Resident</th>
                                <th>Issue</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th>Submitted At</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $c): ?>
                                <?php
                                    $id = (int)($c['id'] ?? 0);
                                    $anon = isset($c['anonymous']) ? (int)$c['anonymous'] : 0;
                                    $residentRaw = $c['resident'] ?? '';
                                    $resident = htmlspecialchars(($anon === 1 || $residentRaw === '') ? 'Anonymous' : $residentRaw);
                                    $issueRaw = $c['issue'] ?? '';
                                    $issueSafe = htmlspecialchars($issueRaw);
                                    $issueShort = (strlen($issueSafe) > 80) ? substr($issueSafe,0,77).'...' : $issueSafe;
                                    $cat = htmlspecialchars($c['category'] ?? '');
                                    $loc = htmlspecialchars($c['location'] ?? '');
                                    $at = htmlspecialchars($c['submitted_at'] ?? '');
                                    $st = htmlspecialchars($c['status'] ?? '');
                                    $label = strtolower($st) === 'resolved' ? 'Resolved' : 'Pending';
                                ?>
                                <tr class="complaint-row" data-id="<?php echo $id; ?>" data-resident="<?php echo $resident; ?>" data-issue="<?php echo $issueSafe; ?>" data-cat="<?php echo $cat; ?>" data-loc="<?php echo $loc; ?>" data-at="<?php echo $at; ?>" data-status="<?php echo $label; ?>">
                                    <td><?php echo $resident; ?></td>
                                    <td><?php echo $issueShort; ?></td>
                                    <td><?php echo $cat; ?></td>
                                    <td><?php echo $loc; ?></td>
                                    <td><?php echo $at; ?></td>
                                    <td><?php if ($label === 'Resolved'): ?><span class="badge badge-resolved">Resolved</span><?php else: ?><span class="badge badge-pending">Pending</span><?php endif; ?></td>
                                    <td class="assign-controls"><button class="primary-button complaint-view-btn">View</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($complaints)): ?>
                                <tr><td colspan="7">No complaints logged yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="complaint-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="complaint-status-tracker-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Complaint Status Tracker</div>
                        <button class="secondary-button" id="status-back">Back to Dashboard</button>
                    </div>
                    <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
                        <span class="badge badge-pending" id="pending-count">Pending: 0</span>
                        <span class="badge badge-resolved" id="resolved-count">Resolved: 0</span>
                        <div style="flex:1"></div>
                        <button class="secondary-button" id="filter-all">All</button>
                        <button class="secondary-button" id="filter-pending">Pending</button>
                        <button class="secondary-button" id="filter-resolved">Resolved</button>
                    </div>
                    <table class="assign-table" id="complaint-status-table">
                        <thead>
                            <tr>
                                <th>Resident</th>
                                <th>Issue</th>
                                <th>Submitted At</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaints as $c): ?>
                                <?php
                                    $id = (int)($c['id'] ?? 0);
                                    $anon = isset($c['anonymous']) ? (int)$c['anonymous'] : 0;
                                    $residentRaw = $c['resident'] ?? '';
                                    $resident = htmlspecialchars(($anon === 1 || $residentRaw === '') ? 'Anonymous' : $residentRaw);
                                    $issueRaw = $c['issue'] ?? '';
                                    $issueSafe = htmlspecialchars($issueRaw);
                                    $issueShort = (strlen($issueSafe) > 80) ? substr($issueSafe,0,77).'...' : $issueSafe;
                                    $at = htmlspecialchars($c['submitted_at'] ?? '');
                                    $st = htmlspecialchars($c['status'] ?? '');
                                    $label = strtolower($st) === 'resolved' ? 'Resolved' : 'Pending';
                                ?>
                                <tr class="complaint-status-row" data-id="<?php echo $id; ?>" data-resident="<?php echo $resident; ?>" data-issue="<?php echo $issueSafe; ?>" data-at="<?php echo $at; ?>" data-status="<?php echo $label; ?>">
                                    <td><?php echo $resident; ?></td>
                                    <td><?php echo $issueShort; ?></td>
                                    <td><?php echo $at; ?></td>
                                    <td><?php if ($label === 'Resolved'): ?><span class="badge badge-resolved">Resolved</span><?php else: ?><span class="badge badge-pending">Pending</span><?php endif; ?></td>
                                    <td class="assign-controls"><button class="primary-button status-resolve-btn">Resolved</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($complaints)): ?>
                                <tr><td colspan="5">No complaints logged yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="status-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="complaint-analytics-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Complaint Analytics</div>
                        <button class="secondary-button" id="analytics-back">Back to Dashboard</button>
                    </div>
                    <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap; margin-bottom:12px;">
                        <canvas id="complaint-pie" width="420" height="420" style="border-radius:12px;"></canvas>
                        <div>
                            <div class="badge badge-pending" id="analytics-pending">Pending: 0</div>
                            <div class="badge badge-resolved" id="analytics-resolved" style="margin-top:6px;">Resolved: 0</div>
                            <ul id="complaint-legend" style="margin-top:10px; list-style:none; padding:0;">
                                <?php foreach ($complaint_analytics_rows as $a): ?>
                                    <li data-cat="<?php echo htmlspecialchars($a['cat'] ?? ''); ?>" data-loc="<?php echo htmlspecialchars($a['loc'] ?? ''); ?>" style="margin:4px 0;">
                                        <span style="opacity:.8; margin-right:8px;"><?php echo htmlspecialchars($a['concern'] ?? ''); ?></span>
                                        <span class="badge" style="background:#111827;color:#fff;"><?php echo (int)($a['reports'] ?? 0); ?></span>
                                        <button class="secondary-button legend-view-btn" style="margin-left:8px;">View</button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <table class="assign-table" id="complaint-analytics-table" style="display:none;">
                        <thead>
                            <tr>
                                <th>Concern</th>
                                <th>Reports</th>
                                <th>Pending</th>
                                <th>Resolved</th>
                                <th>Last Reported</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($complaint_analytics_rows as $a): ?>
                                <?php
                                    $cat = htmlspecialchars($a['cat'] ?? '');
                                    $loc = htmlspecialchars($a['loc'] ?? '');
                                    $concern = htmlspecialchars($a['concern'] ?? '');
                                    $reports = (int)($a['reports'] ?? 0);
                                    $pending = (int)($a['pending'] ?? 0);
                                    $resolved = (int)($a['resolved'] ?? 0);
                                    $last = htmlspecialchars($a['last'] ?? '');
                                ?>
                                <tr class="analytics-row" data-cat="<?php echo $cat; ?>" data-loc="<?php echo $loc; ?>">
                                    <td><?php echo $concern; ?></td>
                                    <td><?php echo $reports; ?></td>
                                    <td><?php echo $pending; ?></td>
                                    <td><?php echo $resolved; ?></td>
                                    <td><?php echo $last; ?></td>
                                    <td class="assign-controls"><button class="primary-button analytics-view-btn">View</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($complaint_analytics_rows)): ?>
                                <tr><td colspan="6">No analytics available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="analytics-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="volunteer-registry-db-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Volunteer Registry Database</div>
                        <button class="secondary-button" id="volreg-back">Back to Dashboard</button>
                    </div>
                    <table class="assign-table" id="volreg-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Zone</th>
                                <th>Availability</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($volunteers as $v): ?>
                                <?php
                                    $id = (int)($v['id'] ?? 0);
                                    $name = htmlspecialchars($v['name'] ?? '');
                                    $roleLabel = htmlspecialchars($v['role'] ?? '');
                                    $contact = htmlspecialchars($v['contact'] ?? '');
                                    $email = htmlspecialchars($v['email'] ?? '');
                                    $zone = htmlspecialchars($v['zone'] ?? '');
                                    $avail = htmlspecialchars($v['availability'] ?? '');
                                ?>
                                <tr class="volreg-row" data-id="<?php echo $id; ?>" data-name="<?php echo $name; ?>" data-role="<?php echo $roleLabel; ?>" data-contact="<?php echo $contact; ?>" data-email="<?php echo $email; ?>" data-zone="<?php echo $zone; ?>" data-availability="<?php echo $avail; ?>"
                                    data-days="<?php echo htmlspecialchars($v['preferred_days'] ?? ''); ?>"
                                    data-slots="<?php echo htmlspecialchars($v['time_slots'] ?? ''); ?>"
                                    data-night="<?php echo isset($v['night_duty']) ? (int)$v['night_duty'] : 0; ?>"
                                    data-max="<?php echo isset($v['max_hours']) ? (int)$v['max_hours'] : 0; ?>"
                                    data-roles="<?php echo htmlspecialchars($v['role_prefs'] ?? ''); ?>"
                                    data-skills="<?php echo htmlspecialchars($v['skills'] ?? ''); ?>"
                                    data-prev="<?php echo isset($v['previous_volunteer']) ? (int)$v['previous_volunteer'] : 0; ?>"
                                    data-prevorg="<?php echo htmlspecialchars($v['prev_org'] ?? ''); ?>"
                                    data-years="<?php echo isset($v['years_experience']) ? (int)$v['years_experience'] : 0; ?>"
                                    data-fit="<?php echo isset($v['physical_fit']) ? (int)$v['physical_fit'] : ''; ?>"
                                    data-med="<?php echo htmlspecialchars($v['medical_conditions'] ?? ''); ?>"
                                    data-long="<?php echo isset($v['long_period']) ? (int)$v['long_period'] : ''; ?>"
                                    data-idurl="<?php echo htmlspecialchars($v['valid_id_url'] ?? ''); ?>"
                                    data-status="<?php echo htmlspecialchars($v['status'] ?? ''); ?>"
                                    data-created="<?php echo htmlspecialchars($v['created_at'] ?? ''); ?>"
                                >
                                    <td><?php echo $name; ?></td>
                                    <td><?php echo $roleLabel; ?></td>
                                    <td><?php echo $contact; ?></td>
                                    <td><?php echo $email; ?></td>
                                    <td><?php echo $zone; ?></td>
                                    <td><?php echo $avail; ?></td>
                                    <td class="assign-controls"><button class="primary-button volreg-view-btn">View</button><button class="primary-button volreg-accept-btn">Accept</button><button class="secondary-button volreg-decline-btn">Decline</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($volunteers)): ?>
                                <tr><td colspan="7">No volunteers found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="volreg-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="duty-roster-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Duty Roster</div>
                        <button class="secondary-button" id="duty-back">Back to Dashboard</button>
                    </div>
                    <table class="assign-table" id="duty-table">
                        <thead>
                            <tr>
                                <th>Volunteer</th>
                                <th>Schedule</th>
                                <th>Date</th>
                                <th>Time Range</th>
                                <th>Zone/Location</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($volunteers as $v): ?>
                                <?php
                                    $id = (int)($v['id'] ?? 0);
                                    $name = htmlspecialchars($v['name'] ?? '');
                                    $roleLabel = htmlspecialchars($v['role'] ?? '');
                                    $zone = htmlspecialchars($v['zone'] ?? '');
                                ?>
                                <tr class="duty-row" data-id="<?php echo $id; ?>" data-name="<?php echo $name; ?>" data-role="<?php echo $roleLabel; ?>">
                                    <td><?php echo $name; ?></td>
                                    <td>
                                        <select class="input-text duty-type">
                                            <option value="Patrol">Patrol</option>
                                            <option value="Event">Event</option>
                                            <option value="Emergency">Emergency</option>
                                        </select>
                                    </td>
                                    <td><input type="date" class="input-text duty-date"></td>
                                    <td><input type="text" class="input-text duty-time" placeholder="18:00 - 22:00"></td>
                                    <td><input type="text" class="input-text duty-zone" value="<?php echo $zone; ?>" placeholder="Zone or Location"></td>
                                    <td class="assign-controls"><button class="primary-button duty-assign-btn">Assign</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($volunteers)): ?>
                                <tr><td colspan="6">No volunteers found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="duty-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="attendance-logs-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Attendance Logs</div>
                        <button class="secondary-button" id="attendance-back">Back to Dashboard</button>
                    </div>
                    <table class="assign-table" id="attendance-table">
                        <thead>
                            <tr>
                                <th>Volunteer</th>
                                <th>Status</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Participation</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($volunteers as $v): ?>
                                <?php
                                    $id = (int)($v['id'] ?? 0);
                                    $name = htmlspecialchars($v['name'] ?? '');
                                    $roleLabel = htmlspecialchars($v['role'] ?? '');
                                ?>
                                <tr class="attendance-row" data-id="<?php echo $id; ?>" data-name="<?php echo $name; ?>" data-role="<?php echo $roleLabel; ?>">
                                    <td><?php echo $name; ?></td>
                                    <td><span class="badge badge-inactive">Checked Out</span></td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td>0</td>
                                    <td class="assign-controls">
                                        <button class="primary-button att-checkin-btn">Check In</button>
                                        <button class="secondary-button att-checkout-btn">Check Out</button>
                                        <button class="secondary-button att-participate-btn">Add Participation</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($volunteers)): ?>
                                <tr><td colspan="6">No volunteers found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="attendance-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="task-assignment-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Task Assignment</div>
                        <button class="secondary-button" id="task-back">Back to Dashboard</button>
                    </div>
                    <table class="assign-table" id="task-table">
                        <thead>
                            <tr>
                                <th>Volunteer</th>
                                <th>Task</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Notes</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($volunteers as $v): ?>
                                <?php
                                    $id = (int)($v['id'] ?? 0);
                                    $name = htmlspecialchars($v['name'] ?? '');
                                    $roleLabel = htmlspecialchars($v['role'] ?? '');
                                ?>
                                <tr class="task-row" data-id="<?php echo $id; ?>" data-name="<?php echo $name; ?>" data-role="<?php echo $roleLabel; ?>">
                                    <td><?php echo $name; ?></td>
                                    <td>
                                        <select class="input-text task-type">
                                            <option value="Traffic Duty">Traffic Duty</option>
                                            <option value="Event Assistance">Event Assistance</option>
                                            <option value="Crowd Control">Crowd Control</option>
                                            <option value="Patrol Support">Patrol Support</option>
                                        </select>
                                    </td>
                                    <td><input type="date" class="input-text task-date"></td>
                                    <td><input type="text" class="input-text task-time" placeholder="09:00 - 12:00"></td>
                                    <td><input type="text" class="input-text task-notes" placeholder="Optional notes"></td>
                                    <td class="assign-controls"><button class="primary-button task-assign-btn">Assign Task</button></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($volunteers)): ?>
                                <tr><td colspan="6">No volunteers found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="details-panel" id="task-details" style="display:none;"></div>
                </div>
            </div>
            <div class="content-section" id="route-mapping-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Route Mapping</div>
                    </div>
                    <iframe id="route-mapping-frame" src="Route%20Mapping.php" title="Route Mapping" scrolling="no" style="width:100%;min-height:720px;border:0;border-radius:12px;background:transparent;"></iframe>
                </div>
            </div>
            <div class="content-section" id="gps-tracking-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">GPS Tracking</div>
                        <button class="secondary-button" id="gps-back">Back to Dashboard</button>
                    </div>
                    <div class="stats-grid" style="margin-top:16px;">
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Total Units</span>
                            </div>
                            <div class="stat-value" id="kpi-total-units">0</div>
                            <div class="stat-info"><span>Active tracking</span></div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Active Units</span>
                            </div>
                            <div class="stat-value" id="kpi-active-units">0</div>
                            <div class="stat-info"><span>On patrol / responding</span></div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Offline Units</span>
                            </div>
                            <div class="stat-value" id="kpi-offline-units">0</div>
                            <div class="stat-info"><span>Inactive devices</span></div>
                        </div>
                    </div>
                    <div style="margin-top:16px;display:grid;grid-template-columns:280px 1fr 360px;gap:16px;align-items:start;">
                        <div class="card">
                            <h2 class="card-title">Unit Status</h2>
                            <div style="margin-top:8px;display:grid;grid-template-columns:1fr;gap:8px;">
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <span>On Patrol</span>
                                    <span class="badge badge-active" id="count-on-patrol">0</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <span>Responding</span>
                                    <span class="badge badge-active" id="count-responding">0</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <span>Stationary</span>
                                    <span class="badge badge-inactive" id="count-stationary">0</span>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center;">
                                    <span>Needs Assistance</span>
                                    <span class="badge badge-pending" id="count-alerts">0</span>
                                </div>
                            </div>
                            <h3 class="card-title" style="margin-top:16px;">Monitoring Units</h3>
                            <div id="unit-list" style="margin-top:8px;display:flex;flex-direction:column;gap:8px;max-height:380px;overflow:auto;"></div>
                        </div>
                        <div>
                            <div class="card">
                                <h2 class="card-title">Map</h2>
                                <div style="margin-top:8px;">
                                    <iframe id="gps-map-embed" src="https://maps.google.com/maps?q=14.6970,121.0880&z=16&output=embed" title="Map - Barangay Location" scrolling="no" style="width:100%;min-height:560px;border:0;border-radius:12px;background:transparent;"></iframe>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <h2 class="card-title">Unit Information</h2>
                            <div id="unit-info-panel" style="margin-top:8px;line-height:1.6;">
                                <div><strong id="ui-name">—</strong> <span class="badge badge-inactive" id="ui-status">—</span></div>
                                <div style="color:#6b7280;">Assignment: <span id="ui-assignment">—</span></div>
                                <div>Location: <span id="ui-location">—</span></div>
                                <div>Speed: <span id="ui-speed">—</span></div>
                                <div>Battery: <span id="ui-battery">—</span></div>
                                <div>Distance Today: <span id="ui-distance">—</span></div>
                                <div>Last Update: <span id="ui-last">—</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="card" style="margin-top:16px;">
                        <h2 class="card-title">Create Patrol Unit</h2>
                        <form id="create-unit-form" style="margin-top:12px;">
                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                                <div>
                                    <label style="display:block;font-weight:600;margin-bottom:6px;">Unit ID</label>
                                    <input type="text" class="input-text" id="unit-id-input" placeholder="UNIT-001" required>
                                </div>
                                <div>
                                    <label style="display:block;font-weight:600;margin-bottom:6px;">Callsign</label>
                                    <input type="text" class="input-text" id="callsign-input" placeholder="Alpha One" required>
                                </div>
                                <div class="full-width" style="grid-column:1 / -1;">
                                    <label style="display:block;font-weight:600;margin-bottom:6px;">Assignment Area</label>
                                    <input type="text" class="input-text" id="assignment-input" placeholder="e.g., Zone 1 - Main Road" required>
                                </div>
                                <div>
                                    <label style="display:block;font-weight:600;margin-bottom:6px;">Unit Type</label>
                                    <select class="input-text" id="unit-type-input" required>
                                        <option value="Mobile Patrol">Mobile Patrol</option>
                                        <option value="Ronda">Ronda</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block;font-weight:600;margin-bottom:6px;">Status</label>
                                    <select class="input-text" id="status-input">
                                        <option value="On Patrol">On Patrol</option>
                                        <option value="Responding">Responding</option>
                                        <option value="Stationary">Stationary</option>
                                        <option value="Needs Assistance">Needs Assistance</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block;font-weight:600;margin-bottom:6px;">Latitude</label>
                                    <input type="number" step="0.000001" class="input-text" id="latitude-input" value="14.697000" required>
                                </div>
                                <div>
                                    <label style="display:block;font-weight:600;margin-bottom:6px;">Longitude</label>
                                    <input type="number" step="0.000001" class="input-text" id="longitude-input" value="121.088000" required>
                                </div>
                            </div>
                            <div style="margin-top:12px;display:flex;gap:8px;">
                                <button type="submit" class="primary-button">Create Unit</button>
                                <button type="button" class="secondary-button" id="clear-unit-form-btn">Clear</button>
                            </div>
                            <div id="create-unit-message" style="margin-top:10px;font-weight:500;"></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="content-section" id="summary-report-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Summary Reports</div>
                        <button class="secondary-button" id="summary-back">Back to Dashboard</button>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Tanod Duty Reports</h2>
                        <p style="margin-top:8px;line-height:1.6;">Reports submitted after duty in assigned areas.</p>
                        <div style="overflow-x:auto;margin-top:8px;">
                            <table style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date/Time</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Area/Location</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Category</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Report Summary</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($observations)): ?>
                                        <?php foreach ($observations as $o): ?>
                                            <?php
                                                $dt = !empty($o['observed_at']) ? date('M d, Y H:i', strtotime($o['observed_at'])) : '—';
                                                $loc = htmlspecialchars($o['location'] ?? '—');
                                                $cat = htmlspecialchars($o['category'] ?? 'General');
                                                $descShort = htmlspecialchars(mb_strimwidth($o['description'] ?? '—', 0, 140, '…'));
                                                $statusLabel = htmlspecialchars($o['status'] ?? 'Submitted');
                                            ?>
                                            <tr>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo $dt; ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo $loc; ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo $cat; ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo $descShort; ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
                                                    <?php if (strtolower($statusLabel) === 'resolved'): ?>
                                                        <span class="badge badge-resolved">Resolved</span>
                                                    <?php elseif (strtolower($statusLabel) === 'pending'): ?>
                                                        <span class="badge badge-pending">Pending</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-inactive"><?php echo $statusLabel; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" style="padding:14px;">No duty reports logged yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-section" id="registration-system-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Registration System</div>
                        <button class="secondary-button" id="registration-back">Back to Dashboard</button>
                    </div>
                    <?php
                        $event_regs = [];
                        try {
                            $pdo->exec("CREATE TABLE IF NOT EXISTS event_registrations (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                user_id INT NOT NULL,
                                name VARCHAR(255) DEFAULT NULL,
                                address VARCHAR(255) DEFAULT NULL,
                                contact VARCHAR(50) DEFAULT NULL,
                                email VARCHAR(255) DEFAULT NULL,
                                type VARCHAR(100) DEFAULT NULL,
                                skills TEXT DEFAULT NULL,
                                volunteer TINYINT(1) DEFAULT 0,
                                status VARCHAR(20) DEFAULT 'pending',
                                created_at DATETIME NOT NULL
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                            $er_stmt = $pdo->prepare("SELECT er.*, u.first_name, u.middle_name, u.last_name, u.contact AS u_contact, u.email AS u_email FROM event_registrations er LEFT JOIN users u ON er.user_id = u.id ORDER BY er.created_at DESC LIMIT 500");
                            $er_stmt->execute([]);
                            $event_regs = $er_stmt->fetchAll(PDO::FETCH_ASSOC);
                            $er_stmt = null;
                        } catch (Exception $e) {
                            $event_regs = [];
                        }
                    ?>
                    <div class="registry-header" style="margin-top:16px;">
                        <div class="registry-title">Event Registrations</div>
                    </div>
                    <table class="assign-table" id="eventreg-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Email</th>
                                <th>Type</th>
                                <th>Skills</th>
                                <th>Volunteer</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($event_regs as $er): ?>
                                <?php
                                    $rid = (int)($er['id'] ?? 0);
                                    $nameRaw = trim(($er['name'] ?? ''));
                                    if ($nameRaw === '') {
                                        $nameRaw = trim(($er['first_name'] ?? '').' '.($er['middle_name'] ?? '').' '.($er['last_name'] ?? ''));
                                    }
                                    $name = htmlspecialchars($nameRaw !== '' ? $nameRaw : '—');
                                    $contact = htmlspecialchars(($er['contact'] ?? '') !== '' ? $er['contact'] : ($er['u_contact'] ?? ''));
                                    $email = htmlspecialchars(($er['email'] ?? '') !== '' ? $er['email'] : ($er['u_email'] ?? ''));
                                    $type = htmlspecialchars($er['type'] ?? '');
                                    $skills = htmlspecialchars($er['skills'] ?? '');
                                    $vol = isset($er['volunteer']) ? ((int)$er['volunteer'] === 1 ? 'Yes' : 'No') : 'No';
                                    $status = htmlspecialchars($er['status'] ?? 'pending');
                                ?>
                                <tr class="eventreg-row"
                                    data-id="<?php echo $rid; ?>"
                                    data-name="<?php echo $name; ?>"
                                    data-contact="<?php echo $contact; ?>"
                                    data-email="<?php echo $email; ?>"
                                    data-type="<?php echo $type; ?>"
                                    data-skills="<?php echo $skills; ?>"
                                    data-volunteer="<?php echo $vol === 'Yes' ? 1 : 0; ?>"
                                    data-status="<?php echo $status; ?>"
                                >
                                    <td><?php echo $name; ?></td>
                                    <td><?php echo $contact !== '' ? $contact : '—'; ?></td>
                                    <td><?php echo $email !== '' ? $email : '—'; ?></td>
                                    <td><?php echo $type !== '' ? $type : '—'; ?></td>
                                    <td><?php echo $skills !== '' ? $skills : '—'; ?></td>
                                    <td><?php echo $vol; ?></td>
                                    <td>
                                        <?php if ($status === 'accepted'): ?>
                                            <span class="badge badge-active">accepted</span>
                                        <?php elseif ($status === 'declined'): ?>
                                            <span class="badge badge-inactive">declined</span>
                                        <?php else: ?>
                                            <span class="badge badge-pending">pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="assign-controls">
                                        <button class="primary-button eventreg-accept-btn">Accept</button>
                                        <button class="secondary-button eventreg-decline-btn">Decline</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($event_regs)): ?>
                                <tr><td colspan="8">No event registrations found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="content-section" id="event-scheduling-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Event Scheduling</div>
                        <button class="secondary-button" id="event-back">Back to Dashboard</button>
                    </div>
                    <iframe id="event-scheduling-frame" src="Event%20Sheduling.php" title="Event Scheduling" scrolling="no" style="width:100%;min-height:720px;border:0;border-radius:12px;background:transparent;"></iframe>
                </div>
            </div>
            <div class="content-section" id="feedback-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Feedback</div>
                        <button class="secondary-button" id="feedback-back">Back to Dashboard</button>
                    </div>
                    <?php
                        $feedbacks = [];
                        try {
                            $pdo->exec("CREATE TABLE IF NOT EXISTS event_feedbacks (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                user_id INT DEFAULT NULL,
                                name VARCHAR(255) DEFAULT NULL,
                                contact VARCHAR(50) DEFAULT NULL,
                                email VARCHAR(255) DEFAULT NULL,
                                event VARCHAR(255) DEFAULT NULL,
                                rating VARCHAR(20) DEFAULT NULL,
                                comments TEXT DEFAULT NULL,
                                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                            $fb_stmt = $pdo->prepare("SELECT ef.*, u.first_name, u.middle_name, u.last_name FROM event_feedbacks ef LEFT JOIN users u ON ef.user_id = u.id ORDER BY ef.created_at DESC LIMIT 500");
                            $fb_stmt->execute([]);
                            $feedbacks = $fb_stmt->fetchAll(PDO::FETCH_ASSOC);
                            $fb_stmt = null;
                        } catch (Exception $e) {
                            $feedbacks = [];
                        }
                    ?>
                    <div class="registry-header" style="margin-top:16px;">
                        <div class="registry-title">Event Feedback Users</div>
                    </div>
                    <table class="assign-table" id="event-feedback-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Event</th>
                                <th>Rating</th>
                                <th>Comments</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedbacks as $f): ?>
                                <?php
                                    $fname = trim(($f['name'] ?? ''));
                                    if ($fname === '') {
                                        $fname = trim(($f['first_name'] ?? '').' '.($f['middle_name'] ?? '').' '.($f['last_name'] ?? ''));
                                    }
                                    $name = htmlspecialchars($fname !== '' ? $fname : '—');
                                    $email = htmlspecialchars($f['email'] ?? '—');
                                    $contact = htmlspecialchars($f['contact'] ?? '—');
                                    $event = htmlspecialchars($f['event'] ?? '—');
                                    $rating = htmlspecialchars($f['rating'] ?? '—');
                                    $comments = htmlspecialchars($f['comments'] ?? '—');
                                    $submitted = htmlspecialchars(isset($f['created_at']) ? date('M d, Y H:i', strtotime($f['created_at'])) : '—');
                                ?>
                                <tr>
                                    <td><?php echo $name; ?></td>
                                    <td><?php echo $email; ?></td>
                                    <td><?php echo $contact; ?></td>
                                    <td><?php echo $event; ?></td>
                                    <td><?php echo $rating; ?></td>
                                    <td><?php echo $comments; ?></td>
                                    <td><?php echo $submitted; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($feedbacks)): ?>
                                <tr><td colspan="7">No feedback found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="content-section" id="tip-portal-section" style="display:none;">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Anonymous Feedback & Tip Line</div>
                        <button class="secondary-button" id="tip-portal-back">Back to Dashboard</button>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Submitted Tips</h2>
                        <div style="overflow-x:auto;margin-top:8px;">
                            <table style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Title</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Category</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Priority</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Location</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Anonymous</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        $tips = [];
                                        try {
                                            $pdo->exec("CREATE TABLE IF NOT EXISTS tips (
                                                id INT AUTO_INCREMENT PRIMARY KEY,
                                                title VARCHAR(255) NOT NULL,
                                                description TEXT NOT NULL,
                                                category VARCHAR(100) DEFAULT 'Other',
                                                priority VARCHAR(20) DEFAULT 'Medium',
                                                status VARCHAR(30) DEFAULT 'pending',
                                                location VARCHAR(255) DEFAULT NULL,
                                                contact_info VARCHAR(255) DEFAULT NULL,
                                                is_anonymous TINYINT(1) DEFAULT 0,
                                                submitted_by INT,
                                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                                            $stmt = $pdo->query("SELECT id, title, category, priority, status, location, is_anonymous, created_at FROM tips ORDER BY created_at DESC LIMIT 200");
                                            $tips = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        } catch (Exception $e) { $tips = []; }
                                    ?>
                                    <?php if (!empty($tips)): ?>
                                        <?php foreach ($tips as $t): ?>
                                            <tr>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo date('M d, Y H:i', strtotime($t['created_at'] ?? 'now')); ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars($t['title'] ?? ''); ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars($t['category'] ?? 'Other'); ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars($t['priority'] ?? 'Medium'); ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars($t['location'] ?? '—'); ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo (intval($t['is_anonymous'] ?? 0) ? 'Yes' : 'No'); ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars($t['status'] ?? 'pending'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="7" style="padding:14px;">No tips submitted yet.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-section" id="message-encryption-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Messages</h1>
                        <p class="dashboard-subtitle">Chat with Tanod, Secretary, and Captain.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="message-encryption-back">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid" style="grid-template-columns: 320px 1fr;">
                    <div class="left-column">
                        <div class="card" style="height:100%;">
                            <h2 class="card-title">Contacts</h2>
                            <div style="padding:12px;">
                                <input id="admin-msg-contact-search" class="modal-input" type="text" placeholder="Search contacts...">
                            </div>
                            <div id="admin-msg-contact-list" style="padding:0 12px 12px 12px;max-height:520px;overflow-y:auto;"></div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card" style="height:100%;">
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 12px 0 12px;">
                                <div>
                                    <h2 class="card-title" id="admin-msg-chat-title">Tanod</h2>
                                    <div id="admin-msg-chat-status" style="font-size:14px;color:#10b981;">Online</div>
                                </div>
                                <div style="display:flex;gap:10px;color:#6b7280;">
                                    <span>🗨️</span><span>📞</span><span>⋯</span>
                                </div>
                            </div>
                            <div id="admin-msg-chat" style="padding:12px;max-height:420px;overflow-y:auto;background:#f9fafb;border-radius:8px;margin:12px;"></div>
                            <div style="display:flex;gap:8px;padding:12px;">
                                <input id="admin-msg-input" class="modal-input" type="text" placeholder="Type a message...">
                                <button class="primary-button" id="admin-msg-send-btn" style="min-width:80px;">Send</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-section" id="user-management-section" style="display:none;">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">User Management</div>
                        <div>
                            <button class="secondary-button" id="user-create-open-btn">Create Account</button>
                            <button class="secondary-button" id="user-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Registered Accounts</h2>
                        <div style="padding:12px;">
                            <input id="user-search" class="modal-input" type="text" placeholder="Search by name, email, or role">
                        </div>
                        <div id="user-role-filters" style="display:flex;gap:8px;padding:0 12px 12px 12px;flex-wrap:wrap;">
                            <button type="button" class="secondary-button user-role-filter active" data-role="ALL">All</button>
                            <button type="button" class="secondary-button user-role-filter" data-role="TANOD">Tanod</button>
                            <button type="button" class="secondary-button user-role-filter" data-role="SECRETARY">Secretary</button>
                            <button type="button" class="secondary-button user-role-filter" data-role="CAPTAIN">Captain</button>
                            <button type="button" class="secondary-button user-role-filter" data-role="ADMIN">Admin</button>
                            <button type="button" class="secondary-button user-role-filter" data-role="USER">User</button>
                        </div>
                        <div style="overflow-x:auto;margin-top:8px;">
                            <table style="width:100%;border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Name</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Username / Email</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Role</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Verified</th>
                                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Created</th>
                                    </tr>
                                </thead>
                                <tbody id="user-tbody">
                                    <?php
                                        $users = [];
                                        try {
                                            $stmtU = $pdo->prepare("SELECT id, first_name, middle_name, last_name, username, email, role, is_verified, created_at FROM users ORDER BY created_at DESC LIMIT 200");
                                            $stmtU->execute([]);
                                            $users = $stmtU->fetchAll(PDO::FETCH_ASSOC);
                                        } catch (Exception $e) { $users = []; }
                                    ?>
                                    <?php if (!empty($users)): ?>
                                        <?php foreach ($users as $u): ?>
                                            <tr>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars(trim(($u['first_name']??'').' '.($u['middle_name']??'').' '.($u['last_name']??''))); ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars(($u['username']??'').' • '.($u['email']??'')); ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars($u['role'] ?? 'USER'); ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo (intval($u['is_verified'] ?? 0) ? '<span class="badge badge-active">Yes</span>' : '<span class="badge badge-pending">No</span>'); ?></td>
                                                <td style="padding:10px;border-bottom:1px solid #f1f5f9;"><?php echo htmlspecialchars(date('M d, Y g:i A', strtotime($u['created_at'] ?? 'now'))); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" style="padding:14px;">No users found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <style>
                    #user-create-modal .modal-input { font-size:16px; padding:12px 12px; }
                    #user-create-modal input::placeholder { font-size:16px; opacity:.8; }
                    #user-create-modal select.modal-input { font-size:16px; }
                </style>
                <div id="user-create-modal" style="position:fixed;left:0;top:0;width:100%;height:100%;display:none;align-items:center;justify-content:center;background:rgba(17,24,39,.25);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:1000;">
                    <div style="background:#fff;width:640px;max-width:90%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.15);">
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #e5e7eb;">
                            <div style="font-weight:600;">Create Account</div>
                            <button class="secondary-button" id="user-create-close">Close</button>
                        </div>
                        <form id="user-create-form" style="padding:16px;">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <input class="modal-input" type="text" name="first_name" placeholder="First name" required>
                                <input class="modal-input" type="text" name="middle_name" placeholder="Middle name">
                                <input class="modal-input" type="text" name="last_name" placeholder="Last name" required>
                                <input class="modal-input" type="text" name="username" placeholder="Username" required>
                                <input class="modal-input" type="email" name="email" placeholder="Email" required>
                                <input class="modal-input" type="password" name="password" placeholder="Password" required>
                                <input class="modal-input" type="text" name="contact" placeholder="Contact" required>
                                <input class="modal-input" type="date" name="date_of_birth" placeholder="Date of birth" required>
                                <select class="modal-input" name="role" required>
                                    <option value="TANOD">Tanod</option>
                                    <option value="SECRETARY">Secretary</option>
                                    <option value="CAPTAIN">Captain</option>
                                </select>
                                <input class="modal-input" type="text" name="address" placeholder="Address" required style="grid-column:1 / span 2;">
                            </div>
                            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                                <button type="button" class="secondary-button" id="user-create-cancel">Cancel</button>
                                <button type="submit" class="primary-button" id="user-create-submit">Create</button>
                            </div>
                            <div id="user-create-status" style="margin-top:8px;font-weight:500;"></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="content-section" id="settings-profile-section">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Profile Settings</h1>
                        <p class="dashboard-subtitle">Manage your personal information and account details</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="profile-back-btn">Back to Dashboard</button>
                        <button class="primary-button" id="profile-save-btn">Save Changes</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Personal Information</h2>
                            <form id="profile-form">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <div class="readonly-field" id="profile-fullname"><?php echo $full_name; ?></div>
                                    <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                                    <input type="hidden" name="middle_name" value="<?php echo htmlspecialchars($middle_name); ?>">
                                    <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Username</label>
                                    <div class="readonly-field" id="profile-username"><?php echo $username; ?></div>
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <div class="readonly-field" id="profile-email"><?php echo $email; ?></div>
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                    <button class="btn-outline" id="change-email-btn" style="margin-top: 8px; padding: 8px 12px;">
                                        <i class='bx bxs-edit'></i> Change
                                    </button>
                                </div>
                                <div class="form-group">
                                    <label>Contact Number</label>
                                    <input type="tel" name="contact" id="profile-contact" class="modal-input" value="<?php echo $contact; ?>" placeholder="Enter contact number">
                                </div>
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" name="date_of_birth" id="profile-dob" class="modal-input" value="<?php echo $date_of_birth; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Address</label>
                                    <textarea name="address" id="profile-address" class="modal-textarea" rows="3" placeholder="Enter address"><?php echo $address; ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Profile Picture</label>
                                    <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                                        <img id="profile-avatar-preview" src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Avatar" style="width:80px;height:80px;border-radius:50%;object-fit:cover;">
                                        <div style="flex: 1;">
                                            <input type="file" id="profile-avatar-input" class="modal-input" accept="image/*">
                                            <small style="color: #6b7280; display: block; margin-top: 5px;">Max file size: 5MB. Allowed: JPG, PNG, WEBP</small>
                                            <button type="button" class="secondary-button" id="profile-avatar-upload-btn" style="margin-top:8px;">Upload</button>
                                            <div id="avatar-status" style="font-weight:500;margin-top:6px;"></div>
                                        </div>
                                    </div>
                                </div>
                                <div id="profile-status" style="margin-top:8px;font-weight:500;"></div>
                            </form>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Account Information</h2>
                            <div class="form-group">
                                <label>User ID</label>
                                <div class="readonly-field">U<?php echo sprintf('%04d', $user_id); ?></div>
                            </div>
                            <div class="form-group">
                                <label>Account Role</label>
                                <div class="readonly-field"><?php echo $role; ?></div>
                            </div>
                            <div class="form-group">
                                <label>Account Created</label>
                                <div class="readonly-field" id="profile-created">Loading...</div>
                            </div>
                            <div class="form-group">
                                <label>Last Updated</label>
                                <div class="readonly-field" id="profile-updated">Loading...</div>
                            </div>
                        </div>
                        <div class="card">
                            <h2 class="card-title">Account Actions</h2>
                            <button class="btn-outline" id="export-data-btn" style="width: 100%; margin-bottom: 10px; padding: 10px;">
                                <i class='bx bxs-download'></i> Export My Data
                            </button>
                            <button class="btn-outline" id="deactivate-account-btn" style="width: 100%; margin-bottom: 10px; padding: 10px;">
                                <i class='bx bxs-user-x'></i> Deactivate Account
                            </button>
                            <small style="color: #6b7280; display: block; margin-top: 10px;">
                                Note: Changes to profile information require admin approval.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-section" id="settings-security-section">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Security Settings</h1>
                        <p class="dashboard-subtitle">Manage your account security and access preferences</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="security-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <div style="display:flex;flex-direction:column;gap:16px;">
                                <div class="security-item">
                                    <div style="display:flex;align-items:center;justify-content:space-between;">
                                        <div>
                                            <h3 class="card-title" style="margin:0;">Change Password</h3>
                                            <div id="pwd-last-changed" style="color:#6b7280;font-size:14px;">Last changed 3 months ago</div>
                                        </div>
                                        <button class="primary-button" id="security-change-password-btn"><i class='bx bxs-key'></i> Change</button>
                                    </div>
                                    <p style="margin-top:8px;color:#6b7280;">Ensure your account is using a long, random password to stay secure.</p>
                                </div>
                                <div class="security-item">
                                    <div style="display:flex;align-items:center;justify-content:space-between;">
                                        <div>
                                            <h3 class="card-title" style="margin:0;">Email Address</h3>
                                            <div id="email-address" style="color:#6b7280;"><?php echo htmlspecialchars($_SESSION['user_email'] ?? $email); ?></div>
                                        </div>
                                        <button class="secondary-button" id="security-change-email-btn"><i class='bx bxs-edit'></i> Change</button>
                                    </div>
                                    <p style="margin-top:8px;color:#6b7280;">Your email address is used for account notifications and password resets.</p>
                                </div>
                                <div class="security-item">
                                    <div style="display:flex;align-items:center;justify-content:space-between;">
                                        <div>
                                            <h3 class="card-title" style="margin:0;">API Access</h3>
                                            <div id="api-status" style="color:#6b7280;"><span class="badge badge-info">No API key generated</span></div>
                                        </div>
                                        <div style="display:flex;gap:10px;">
                                            <button class="secondary-button" id="security-generate-key-btn"><i class='bx bxs-plus-circle'></i> Generate Key</button>
                                            <button class="secondary-button" id="security-enable-api-btn"><i class='bx bxs-power-off'></i> Enable</button>
                                        </div>
                                    </div>
                                    <p style="margin-top:8px;color:#6b7280;">API keys allow external applications to access your data. Generate with caution.</p>
                                </div>
                                <div class="security-item">
                                    <div style="display:flex;align-items:center;justify-content:space-between;">
                                        <div>
                                            <h3 class="card-title" style="margin:0;">Two-Factor Authentication</h3>
                                            <div id="tfa-status" style="color:#6b7280;"><span class="badge badge-danger">Disabled</span></div>
                                        </div>
                                        <button class="secondary-button" id="security-enable-2fa-btn"><i class='bx bxs-lock-alt'></i> Enable 2FA</button>
                                    </div>
                                    <p style="margin-top:8px;color:#6b7280;">Add an extra layer of security to your account by enabling two-factor authentication.</p>
                                </div>
                                <div class="danger-zone" style="margin-top:8px;padding:16px;border-radius:12px;background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;">
                                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;"><i class='bx bxs-error-circle'></i><h3 class="card-title" style="margin:0;">Danger Zone</h3></div>
                                    <p style="margin-top:4px;color:#b91c1c;">Once you delete your account, there is no going back. Please be certain.</p>
                                    <button class="secondary-button" id="security-delete-account-btn" style="margin-top:8px;background:#ef4444;color:#fff;border-color:#ef4444;"><i class='bx bxs-trash'></i> Delete Account</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Security Status</h2>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <div style="display:flex;justify-content:space-between;"><span>Password Strength:</span><span style="font-weight:600;color:#16a34a;">Strong</span></div>
                                <div style="display:flex;justify-content:space-between;"><span>Account Activity:</span><span style="font-weight:600;color:#16a34a;">Normal</span></div>
                                <div style="display:flex;justify-content:space-between;"><span>Login Devices:</span><span>1 device</span></div>
                                <div style="display:flex;justify-content:space-between;"><span>Last Login:</span><span id="last-login-time">Just now</span></div>
                            </div>
                        </div>
                        <div class="card">
                            <h2 class="card-title">Active Sessions</h2>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#e5e7eb;"><i class='bx bx-desktop'></i></div>
                                <div style="flex:1;">
                                    <p style="margin:0;font-weight:600;">Chrome on Windows</p>
                                    <p style="margin:0;color:#6b7280;">Current session • <?php echo date('M d, Y H:i'); ?></p>
                                </div>
                                <button class="secondary-button" style="padding:6px 10px;">End</button>
                            </div>
                        </div>
                        <div class="card">
                            <h2 class="card-title">Security Tips</h2>
                            <ul style="margin:0;padding-left:18px;color:#374151;">
                                <li>Use a unique password for this account</li>
                                <li>Enable two-factor authentication for extra security</li>
                                <li>Regularly update your password</li>
                                <li>Log out from devices you don't recognize</li>
                                <li>Never share your password with anyone</li>
                            </ul>
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
            item.addEventListener('click', function(e) {
                const target = this.getAttribute('data-target');
                if (target) {
                    e.preventDefault();
                    document.querySelectorAll('.submenu-item').forEach(i => { i.classList.remove('active'); });
                    this.classList.add('active');
                    document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                    const el = document.getElementById(target);
                    if (el) el.style.display = 'block';
                    if (target === 'gps-tracking-section') {
                        if (typeof loadGPSUnits === 'function') loadGPSUnits();
                    }
                }
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
        const settingsLinks = document.querySelectorAll('#sidebar-settings-submenu .submenu-item');
        if (settingsLinks && settingsLinks.length) {
            settingsLinks.forEach(function(link){
                link.addEventListener('click', function(){
                    if (typeof closeSidebarSettings === 'function') closeSidebarSettings();
                });
            });
        }
        
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

        const dashboardMenu = document.getElementById('dashboard-menu');
        if (dashboardMenu) {
            dashboardMenu.addEventListener('click', function(e){
                e.preventDefault();
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }
        const userMenu = document.getElementById('user-menu');
        if (userMenu) {
            userMenu.addEventListener('click', function(e){
                e.preventDefault();
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                const el = document.getElementById('user-management-section');
                if (el) el.style.display = 'block';
                if (typeof loadUsers === 'function') loadUsers();
            });
        }
        const settingsProfileBtn = document.getElementById('settings-profile-btn');
        const settingsSecurityBtn = document.getElementById('settings-security-btn');
        const sidebarSettingsProfileLink = document.getElementById('sidebar-settings-profile-link');
        const sidebarSettingsSecurityLink = document.getElementById('sidebar-settings-security-link');
        function showSection(id){ document.querySelectorAll('.content-section').forEach(s => { s.style.display='none'; }); const el=document.getElementById(id); if (el) el.style.display='block'; }
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
        profileInputsStyle.textContent = '#profile-form .modal-input{font-size:18px;padding:14px 12px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;}#profile-form .modal-textarea{font-size:18px;padding:14px 12px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;}#profile-form input::placeholder,#profile-form textarea::placeholder{font-size:16px;color:#6b7280;opacity:.8;}#profile-form input[type=\"date\"]{padding:12px 14px;height:44px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;}';
        document.head.appendChild(profileInputsStyle);
        async function loadProfile(){
            try{
                const fd = new FormData();
                fd.append('action','profile_get');
                const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                        const fullnameEl = document.getElementById('profile-fullname');
                        if (fullnameEl){
                            const fname = String(p.first_name||'').trim();
                            const mname = String(p.middle_name||'').trim();
                            const lname = String(p.last_name||'').trim();
                            fullnameEl.textContent = [fname, mname, lname].filter(Boolean).join(' ');
                        }
                        const unameEl = document.getElementById('profile-username');
                        if (unameEl){ unameEl.textContent = p.username || ''; }
                        const emailEl = document.getElementById('profile-email');
                        if (emailEl){ emailEl.textContent = p.email || ''; }
                        const createdEl = document.getElementById('profile-created');
                        const updatedEl = document.getElementById('profile-updated');
                        const fmt = (s)=>{ try{ const d=new Date(s); return isNaN(d.getTime()) ? '—' : d.toLocaleDateString(undefined,{year:'numeric',month:'long',day:'numeric'}); }catch(_){ return '—'; } };
                        if (createdEl) createdEl.textContent = fmt(p.created_at);
                        if (updatedEl) updatedEl.textContent = fmt(p.updated_at);
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
                    const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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

        const bwcLinks = document.querySelectorAll('#fire-incident .submenu-item');
        if (bwcLinks && bwcLinks.length) {
            if (bwcLinks[0]) bwcLinks[0].setAttribute('data-target','member-registry-section');
            if (bwcLinks[1]) bwcLinks[1].setAttribute('data-target','observation-logging-section');
            if (bwcLinks[2]) bwcLinks[2].setAttribute('data-target','patrol-assignment-section');
        }

        const cctvLinks = document.querySelectorAll('#volunteer .submenu-item');
        if (cctvLinks && cctvLinks.length) {
            if (cctvLinks[0]) cctvLinks[0].setAttribute('data-target','live-viewer-section');
            if (cctvLinks[1]) cctvLinks[1].setAttribute('data-target','evidence-archive-section');
        }

        const compLinks = document.querySelectorAll('#inventory .submenu-item');
        if (compLinks && compLinks.length) {
            if (compLinks[0]) compLinks[0].setAttribute('data-target','complaint-online-form-section');
            if (compLinks[1]) compLinks[1].setAttribute('data-target','complaint-status-tracker-section');
            if (compLinks[2]) compLinks[2].setAttribute('data-target','complaint-analytics-section');
        }

        const trainingLinks = document.querySelectorAll('#training .submenu-item');
        if (trainingLinks && trainingLinks.length) {
            if (trainingLinks[0]) trainingLinks[0].setAttribute('data-target','route-mapping-section');
            if (trainingLinks[1]) trainingLinks[1].setAttribute('data-target','gps-tracking-section');
            if (trainingLinks[2]) trainingLinks[2].setAttribute('data-target','summary-report-section');
        }

        const postincidentLinks = document.querySelectorAll('#postincident .submenu-item');
        if (postincidentLinks && postincidentLinks.length) {
            if (postincidentLinks[0]) postincidentLinks[0].setAttribute('data-target','tip-portal-section');
            if (postincidentLinks[1]) postincidentLinks[1].setAttribute('data-target','message-encryption-section');
        }

        const inspectionLinks = document.querySelectorAll('#inspection .submenu-item');
        if (inspectionLinks && inspectionLinks.length) {
            if (inspectionLinks[0]) inspectionLinks[0].setAttribute('data-target','registration-system-section');
            if (inspectionLinks[1]) inspectionLinks[1].setAttribute('data-target','event-scheduling-section');
            if (inspectionLinks[2]) inspectionLinks[2].setAttribute('data-target','feedback-section');
        }

        const scheduleLinks = document.querySelectorAll('#schedule .submenu-item');
        if (scheduleLinks && scheduleLinks.length) {
            if (scheduleLinks[0]) scheduleLinks[0].setAttribute('data-target','volunteer-registry-db-section');
            if (scheduleLinks[1]) scheduleLinks[1].setAttribute('data-target','duty-roster-section');
            if (scheduleLinks[2]) scheduleLinks[2].setAttribute('data-target','attendance-logs-section');
            if (scheduleLinks[3]) scheduleLinks[3].setAttribute('data-target','task-assignment-section');
        }

        const volregBack = document.getElementById('volreg-back');
        if (volregBack) {
            volregBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const volregTable = document.getElementById('volreg-table');
        if (volregTable) {
            volregTable.addEventListener('click', async function(e){
                const row = e.target.closest('.volreg-row');
                const viewBtn = e.target.closest('.volreg-view-btn');
                const acceptBtn = e.target.closest('.volreg-accept-btn');
                const declineBtn = e.target.closest('.volreg-decline-btn');
                if (!row) return;
                if (viewBtn) {
                    const panel = document.getElementById('volreg-details');
                    panel.style.display = 'block';
                    const name = row.getAttribute('data-name') || '—';
                    const role = row.getAttribute('data-role') || 'Volunteer';
                    const contact = row.getAttribute('data-contact') || '—';
                    const email = row.getAttribute('data-email') || '—';
                    const zone = row.getAttribute('data-zone') || '—';
                    const avail = row.getAttribute('data-availability') || '—';
                    const days = row.getAttribute('data-days') || '—';
                    const slots = row.getAttribute('data-slots') || '—';
                    const night = row.getAttribute('data-night'); const nightLabel = night==='1'?'Yes':(night==='0'?'No':'—');
                    const maxh = row.getAttribute('data-max') || '—';
                    const roles = row.getAttribute('data-roles') || '—';
                    const skills = row.getAttribute('data-skills') || '—';
                    const prev = row.getAttribute('data-prev'); const prevLabel = prev==='1'?'Yes':(prev==='0'?'No':'—');
                    const prevorg = row.getAttribute('data-prevorg') || '—';
                    const years = row.getAttribute('data-years') || '—';
                    const fit = row.getAttribute('data-fit'); const fitLabel = fit==='1'?'Yes':(fit==='0'?'No':'—');
                    const medical = row.getAttribute('data-med') || '—';
                    const longp = row.getAttribute('data-long'); const longLabel = longp==='1'?'Yes':(longp==='0'?'No':'—');
                    const idurl = row.getAttribute('data-idurl') || '';
                    const idSrc = idurl ? ('../' + idurl) : '';
                    const created = row.getAttribute('data-created') || '';
                    const status = row.getAttribute('data-status') || 'pending';
                    const badgeClass = status==='pending' ? 'badge-pending' : (status==='declined' ? 'badge-inactive' : 'badge-active');
                    panel.innerHTML = `
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                                <div>
                                    <div style="font-weight:600;font-size:16px;">${name}</div>
                                    <div style="color:#6b7280;font-size:14px;">${role} • ${zone} • ${avail}</div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-weight:600;">Valid ID</div>
                                    ${idSrc ? `<img src="${idSrc}" alt="Valid ID" style="max-width:160px;max-height:160px;border-radius:8px;border:1px solid #e5e7eb;object-fit:cover;">` : ''}
                                </div>
                            </div>
                            <div style="display:flex;justify-content:flex-end;margin-top:8px;">
                                <button class="secondary-button" id="volreg-details-close">Close</button>
                            </div>
                            <div style="margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                <div>Contact: <strong>${contact}</strong></div>
                                <div>Email: <strong>${email}</strong></div>
                                <div>Preferred Days: <strong>${days}</strong></div>
                                <div>Time Slots: <strong>${slots}</strong></div>
                                <div>Night Duty: <strong>${nightLabel}</strong></div>
                                <div>Max Hours/Week: <strong>${maxh}</strong></div>
                                <div class="full" style="grid-column:1/-1;">Role Preferences: <strong>${roles}</strong></div>
                                <div class="full" style="grid-column:1/-1;">Skills: <strong>${skills}</strong></div>
                                <div>Previous Volunteer: <strong>${prevLabel}</strong></div>
                                <div>Years of Experience: <strong>${years}</strong></div>
                                <div>Physical Fit: <strong>${fitLabel}</strong></div>
                                <div>Long Period Ability: <strong>${longLabel}</strong></div>
                                <div class="full" style="grid-column:1/-1;">Previous Organization: <strong>${prevorg}</strong></div>
                                <div class="full" style="grid-column:1/-1;">Medical Conditions: <strong>${medical}</strong></div>
                                <div>Status: <span id="volreg-status-badge" class="badge ${badgeClass}">${status}</span></div>
                                <div>Applied: <strong>${created}</strong></div>
                            </div>
                        </div>
                    `;
                    const closeBtn = panel.querySelector('#volreg-details-close');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', function(){ panel.style.display = 'none'; });
                    }
                    return;
                }
                async function setStatus(newStatus){
                    const id = row.getAttribute('data-id');
                    try{
                        const res = await fetch('admin_dashboard.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'volunteer_set_status', id, status: newStatus }),
                            credentials: 'same-origin'
                        });
                        const data = await res.json();
                        if (data && data.success){
                            row.setAttribute('data-status', data.status);
                            const panel = document.getElementById('volreg-details');
                            if (panel && panel.style.display !== 'none'){
                                const badge = panel.querySelector('#volreg-status-badge');
                                if (badge){
                                    badge.textContent = data.status;
                                    badge.className = 'badge ' + (data.status==='pending' ? 'badge-pending' : (data.status==='declined' ? 'badge-inactive' : 'badge-active'));
                                }
                            }
                        } else {
                            alert('Failed to update status');
                        }
                    } catch(_){
                        alert('Network error');
                    }
                }
                if (acceptBtn) { await setStatus('accepted'); return; }
                if (declineBtn) { await setStatus('declined'); return; }
            });
        }

        const eventregTable = document.getElementById('eventreg-table');
        if (eventregTable) {
            eventregTable.addEventListener('click', async function(e){
                const row = e.target.closest('.eventreg-row');
                const acceptBtn = e.target.closest('.eventreg-accept-btn');
                const declineBtn = e.target.closest('.eventreg-decline-btn');
                if (!row) return;
                async function setStatus(newStatus){
                    const id = row.getAttribute('data-id');
                    try{
                        const res = await fetch('admin_dashboard.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'event_registration_set_status', id, status: newStatus }),
                            credentials: 'same-origin'
                        });
                        const data = await res.json();
                        if (data && data.success){
                            row.setAttribute('data-status', data.status);
                            const statusCell = row.querySelector('td:nth-child(7)');
                            if (statusCell){
                                if (data.status === 'accepted') {
                                    statusCell.innerHTML = '<span class="badge badge-active">accepted</span>';
                                } else if (data.status === 'declined') {
                                    statusCell.innerHTML = '<span class="badge badge-inactive">declined</span>';
                                } else {
                                    statusCell.innerHTML = '<span class="badge badge-pending">pending</span>';
                                }
                            }
                        } else {
                            alert('Failed to update status');
                        }
                    } catch(_){
                        alert('Network error');
                    }
                }
                if (acceptBtn) { await setStatus('accepted'); return; }
                if (declineBtn) { await setStatus('declined'); return; }
            });
        }

        const dutyBack = document.getElementById('duty-back');
        if (dutyBack) {
            dutyBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const dutyTable = document.getElementById('duty-table');
        const dutyRoster = JSON.parse(localStorage.getItem('duty_roster') || '[]');
        if (dutyTable) {
            dutyTable.addEventListener('click', function(e){
                const btn = e.target.closest('.duty-assign-btn');
                if (!btn) return;
                const row = btn.closest('.duty-row');
                const id = row.getAttribute('data-id');
                const name = row.getAttribute('data-name');
                const role = row.getAttribute('data-role');
                const type = row.querySelector('.duty-type').value;
                const date = row.querySelector('.duty-date').value;
                const time = row.querySelector('.duty-time').value.trim();
                const zone = row.querySelector('.duty-zone').value.trim();
                const payload = { id, name, role, type, date, time, zone, created_at: new Date().toISOString() };
                dutyRoster.push(payload);
                localStorage.setItem('duty_roster', JSON.stringify(dutyRoster));
                const panel = document.getElementById('duty-details');
                panel.style.display = 'block';
                panel.innerHTML = `<div><div style=\"font-weight:600;font-size:16px;\">${name}</div><div style=\"color:#6b7280;font-size:14px;\">${role} • ${type}</div><div style=\"margin-top:10px;\">Date: ${date || '—'} • Time: ${time || '—'}</div><div style=\"margin-top:10px;\">Zone/Location: ${zone || '—'}</div></div>`;
            });
        }

        const attendanceBack = document.getElementById('attendance-back');
        if (attendanceBack) {
            attendanceBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const attendanceTable = document.getElementById('attendance-table');
        const attendanceStore = JSON.parse(localStorage.getItem('attendance_logs') || '{}');
        function updateAttendanceRow(row){
            const id = row.getAttribute('data-id');
            const rec = attendanceStore[id] || {};
            const statusCell = row.querySelector('td:nth-child(2)');
            const inCell = row.querySelector('td:nth-child(3)');
            const outCell = row.querySelector('td:nth-child(4)');
            const partCell = row.querySelector('td:nth-child(5)');
            if (statusCell) statusCell.innerHTML = (rec.status === 'In') ? '<span class="badge badge-active">Checked In</span>' : '<span class="badge badge-inactive">Checked Out</span>';
            if (inCell) inCell.textContent = rec.check_in ? new Date(rec.check_in).toLocaleString() : '—';
            if (outCell) outCell.textContent = rec.check_out ? new Date(rec.check_out).toLocaleString() : '—';
            if (partCell) partCell.textContent = String(rec.participation || 0);
        }

        const routeBack = document.getElementById('route-back');
        if (routeBack) {
            routeBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const gpsBack = document.getElementById('gps-back');
        if (gpsBack) {
            gpsBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const summaryBack = document.getElementById('summary-back');
        if (summaryBack) {
            summaryBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const registrationBack = document.getElementById('registration-back');
        if (registrationBack) {
            registrationBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const eventBack = document.getElementById('event-back');
        if (eventBack) {
            eventBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const feedbackBack = document.getElementById('feedback-back');
        if (feedbackBack) {
            feedbackBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }
        
        const tipPortalBack = document.getElementById('tip-portal-back');
        if (tipPortalBack) {
            tipPortalBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }
        const msgEncBack = document.getElementById('message-encryption-back');
        if (msgEncBack) {
            msgEncBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        function autoResizeIframe(el){
            try{
                const doc = el.contentWindow.document;
                function stripChrome(){
                    try{
                        const style = doc.createElement('style');
                        style.textContent = `
                            html, body { overflow: hidden !important; }
                            .sidebar, aside.sidebar, .header, header.header, .user-profile { display: none !important; }
                            .menu-section, .menu-items, nav { display: none !important; }
                            .content-wrapper, .page, .main, .container { margin: 0 !important; padding-left: 0 !important; width: 100% !important; }
                        `;
                        doc.head.appendChild(style);
                    }catch(_){ /* no-op */ }
                }
                function resize(){
                    const h = Math.max(doc.documentElement.scrollHeight || 0, doc.body.scrollHeight || 0);
                    if (h) el.style.height = h + 'px';
                    el.style.overflow = 'hidden';
                }
                stripChrome();
                resize();
                const RO = el.contentWindow.ResizeObserver || ResizeObserver;
                if (RO){
                    try{ const ro = new RO(function(){ stripChrome(); resize(); }); ro.observe(doc.documentElement); ro.observe(doc.body); }catch(_){ setInterval(function(){ stripChrome(); resize(); },1000); }
                } else {
                    setInterval(function(){ stripChrome(); resize(); },1000);
                }
            }catch(_){}
        }
        const rmf = document.getElementById('route-mapping-frame');
        if (rmf) rmf.addEventListener('load', function(){ autoResizeIframe(rmf); });
        const gpf = document.getElementById('gps-tracking-frame');
        if (gpf) gpf.addEventListener('load', function(){ autoResizeIframe(gpf); });
        const srf = document.getElementById('summary-report-frame');
        if (srf) srf.addEventListener('load', function(){ autoResizeIframe(srf); });
        const rsf = document.getElementById('registration-system-frame');
        if (rsf) rsf.addEventListener('load', function(){ autoResizeIframe(rsf); });
        const esf = document.getElementById('event-scheduling-frame');
        if (esf) esf.addEventListener('load', function(){ autoResizeIframe(esf); });
        const fbf = document.getElementById('feedback-frame');
        if (fbf) fbf.addEventListener('load', function(){ autoResizeIframe(fbf); });
        if (attendanceTable) {
            attendanceTable.querySelectorAll('.attendance-row').forEach(updateAttendanceRow);
            attendanceTable.addEventListener('click', function(e){
                const row = e.target.closest('.attendance-row');
                if (!row) return;
                const id = row.getAttribute('data-id');
                const name = row.getAttribute('data-name');
                const rec = attendanceStore[id] || { participation: 0 };
                if (e.target.closest('.att-checkin-btn')) { rec.check_in = new Date().toISOString(); rec.status = 'In'; }
                else if (e.target.closest('.att-checkout-btn')) { rec.check_out = new Date().toISOString(); rec.status = 'Out'; }
                else if (e.target.closest('.att-participate-btn')) { rec.participation = (rec.participation || 0) + 1; }
                else {
                    const panel = document.getElementById('attendance-details');
                    if (panel) {
                        const statusLabel = rec.status === 'In' ? 'Checked In' : 'Checked Out';
                        panel.style.display = 'block';
                        panel.innerHTML = `<div><div style=\"font-weight:600;font-size:16px;\">${name}</div><div style=\"color:#6b7280;font-size:14px;\">${statusLabel}</div><div style=\"margin-top:10px;\">Check-In: ${rec.check_in ? new Date(rec.check_in).toLocaleString() : '—'}</div><div style=\"margin-top:10px;\">Check-Out: ${rec.check_out ? new Date(rec.check_out).toLocaleString() : '—'}</div><div style=\"margin-top:10px;\">Participation: ${rec.participation || 0}</div></div>`;
                    }
                    return;
                }
                attendanceStore[id] = rec;
                localStorage.setItem('attendance_logs', JSON.stringify(attendanceStore));
                updateAttendanceRow(row);
                const panel = document.getElementById('attendance-details');
                if (panel) {
                    const statusLabel = rec.status === 'In' ? 'Checked In' : 'Checked Out';
                    panel.style.display = 'block';
                    panel.innerHTML = `<div><div style=\"font-weight:600;font-size:16px;\">${name}</div><div style=\"color:#6b7280;font-size:14px;\">${statusLabel}</div><div style=\"margin-top:10px;\">Check-In: ${rec.check_in ? new Date(rec.check_in).toLocaleString() : '—'}</div><div style=\"margin-top:10px;\">Check-Out: ${rec.check_out ? new Date(rec.check_out).toLocaleString() : '—'}</div><div style=\"margin-top:10px;\">Participation: ${rec.participation || 0}</div></div>`;
                }
            });
        }
        
        function updateKPI(stats){
            try{
                const totalEl = document.getElementById('kpi-total-units');
                const activeEl = document.getElementById('kpi-active-units');
                const offlineEl = document.getElementById('kpi-offline-units');
                if (totalEl) totalEl.textContent = String(stats.total_devices || 0);
                if (activeEl) activeEl.textContent = String(stats.active_devices ?? stats.active ?? 0);
                if (offlineEl) offlineEl.textContent = String(stats.offline_devices || 0);
            }catch(_){}
        }
        
        function renderStatus(stats){
            try{
                const onPatrol = document.getElementById('count-on-patrol');
                const responding = document.getElementById('count-responding');
                const stationary = document.getElementById('count-stationary');
                const alerts = document.getElementById('count-alerts');
                if (onPatrol) onPatrol.textContent = String(stats.on_patrol || 0);
                if (responding) responding.textContent = String(stats.responding || 0);
                if (stationary) stationary.textContent = String(stats.stationary || 0);
                if (alerts) alerts.textContent = String(stats.alerts || 0);
            }catch(_){}
        }
        
        function renderUnitList(units){
            try{
                const listEl = document.getElementById('unit-list');
                if (!listEl) return;
                if (!units || !units.length) { listEl.innerHTML = '<div style="padding:12px;color:#6b7280;">No units found</div>'; return; }
                listEl.innerHTML = '';
                units.forEach(u => {
                    const item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'secondary-button';
                    item.style.display = 'flex';
                    item.style.justifyContent = 'space-between';
                    item.style.width = '100%';
                    item.style.textAlign = 'left';
                    const loc = (typeof u.lat !== 'undefined' && typeof u.lng !== 'undefined') ? `${Number(u.lat).toFixed(6)}, ${Number(u.lng).toFixed(6)}` : '—';
                    item.innerHTML = `<span><strong>${u.callsign || u.id}</strong><span style="color:#6b7280;margin-left:8px;font-size:12px;">${loc}</span></span><span class="badge ${String(u.status).toLowerCase().includes('respond') ? 'badge-active' : (String(u.status).toLowerCase().includes('need') ? 'badge-pending' : 'badge-inactive')}">${u.status}</span>`;
                    item.addEventListener('click', function(){ updateUnitInfo(u); });
                    listEl.appendChild(item);
                });
            }catch(_){}
        }
        
        function updateUnitInfo(u){
            try{
                const uiName = document.getElementById('ui-name');
                const uiStatus = document.getElementById('ui-status');
                const uiAssignment = document.getElementById('ui-assignment');
                const uiLocation = document.getElementById('ui-location');
                const uiSpeed = document.getElementById('ui-speed');
                const uiBattery = document.getElementById('ui-battery');
                const uiDistance = document.getElementById('ui-distance');
                const uiLast = document.getElementById('ui-last');
                if (uiName) uiName.textContent = u.callsign || u.id || '—';
                if (uiStatus) { uiStatus.textContent = u.status || '—'; uiStatus.className = `badge ${String(u.status).toLowerCase().includes('respond') ? 'badge-active' : (String(u.status).toLowerCase().includes('need') ? 'badge-pending' : 'badge-inactive')}`; }
                if (uiAssignment) uiAssignment.textContent = u.assignment || '—';
                const loc = (typeof u.lat !== 'undefined' && typeof u.lng !== 'undefined') ? `${Number(u.lat).toFixed(6)}, ${Number(u.lng).toFixed(6)}` : '—';
                if (uiLocation) uiLocation.textContent = loc;
                if (uiSpeed) uiSpeed.textContent = `${Number(u.speed || 0).toFixed(1)} km/h`;
                if (uiBattery) uiBattery.textContent = `${Number(u.battery ?? 0)}%`;
                if (uiDistance) uiDistance.textContent = `${Number(u.distance_today || 0).toFixed(1)} km`;
                if (uiLast) uiLast.textContent = u.last_ping ? new Date(u.last_ping).toLocaleString() : '—';
            }catch(_){}
        }
        
        async function loadGPSUnits(){
            try{
                const res = await fetch('api/gps_data.php', { credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                if (data && data.success) {
                    updateKPI(data.stats || {});
                    renderStatus(data.stats || {});
                    renderUnitList(data.units || []);
                    if (data.units && data.units.length) updateUnitInfo(data.units[0]);
                }
            }catch(_){}
        }
        
        const createUnitForm = document.getElementById('create-unit-form');
        if (createUnitForm){
            const msg = document.getElementById('create-unit-message');
            const clearBtn = document.getElementById('clear-unit-form-btn');
            if (clearBtn){
                clearBtn.addEventListener('click', function(){
                    createUnitForm.reset();
                    const latEl = document.getElementById('latitude-input');
                    const lngEl = document.getElementById('longitude-input');
                    if (latEl) latEl.value = '14.697000';
                    if (lngEl) lngEl.value = '121.088000';
                    if (msg) { msg.textContent = ''; msg.style.color = ''; }
                });
            }
            createUnitForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const unitId = document.getElementById('unit-id-input').value.trim();
                const callsign = document.getElementById('callsign-input').value.trim();
                const assignmentBase = document.getElementById('assignment-input').value.trim();
                const unitType = document.getElementById('unit-type-input').value;
                const status = document.getElementById('status-input').value;
                const latitude = parseFloat(document.getElementById('latitude-input').value);
                const longitude = parseFloat(document.getElementById('longitude-input').value);
                if (!unitId || !callsign || !assignmentBase || Number.isNaN(latitude) || Number.isNaN(longitude)) return;
                const payload = {
                    unit_id: unitId,
                    callsign: callsign,
                    assignment: `${assignmentBase} - ${unitType}`,
                    latitude: latitude,
                    longitude: longitude,
                    status: status,
                    speed: 0,
                    battery: 100,
                    distance_today: 0
                };
                try{
                    const res = await fetch('api/gps_save.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    });
                    const data = await res.json();
                    if (msg) {
                        msg.textContent = data && data.success ? 'Unit created successfully' : (data && data.error ? data.error : 'Failed to create unit');
                        msg.style.color = data && data.success ? '#16a34a' : '#dc2626';
                    }
                    if (data && data.success) {
                        createUnitForm.reset();
                        const latEl = document.getElementById('latitude-input');
                        const lngEl = document.getElementById('longitude-input');
                        if (latEl) latEl.value = '14.697000';
                        if (lngEl) lngEl.value = '121.088000';
                        loadGPSUnits();
                    }
                }catch(_){
                    if (msg) { msg.textContent = 'Network error'; msg.style.color = '#dc2626'; }
                }
            });
        }
        
        loadGPSUnits();
        
        const adminMsgContactSearch = document.getElementById('admin-msg-contact-search');
        const adminMsgContactList = document.getElementById('admin-msg-contact-list');
        const adminMsgChat = document.getElementById('admin-msg-chat');
        const adminMsgInput = document.getElementById('admin-msg-input');
        const adminMsgSendBtn = document.getElementById('admin-msg-send-btn');
        const adminMsgChatTitle = document.getElementById('admin-msg-chat-title');
        const adminMsgChatStatus = document.getElementById('admin-msg-chat-status');
        const adminContacts = [
            { id:'TANOD', name:'Tanod', online:true },
            { id:'SECRETARY', name:'Secretary', online:true },
            { id:'CAPTAIN', name:'Captain', online:true }
        ];
        let selectedRole = 'TANOD';
        function renderAdminContacts(){
            if (!adminMsgContactList) return;
            const q = (adminMsgContactSearch && adminMsgContactSearch.value || '').toLowerCase();
            adminMsgContactList.innerHTML = '';
            adminContacts.filter(c => !q || c.name.toLowerCase().includes(q)).forEach(c => {
                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.alignItems = 'center';
                row.style.justifyContent = 'space-between';
                row.style.padding = '10px';
                row.style.borderRadius = '8px';
                row.style.cursor = 'pointer';
                row.style.marginBottom = '6px';
                row.style.background = selectedRole === c.id ? '#eef2ff' : '#fff';
                const left = document.createElement('div');
                left.style.display = 'flex';
                left.style.alignItems = 'center';
                const avatar = document.createElement('div');
                avatar.textContent = c.name.charAt(0).toUpperCase();
                avatar.style.width = '32px';
                avatar.style.height = '32px';
                avatar.style.borderRadius = '50%';
                avatar.style.background = '#e5e7eb';
                avatar.style.display = 'flex';
                avatar.style.alignItems = 'center';
                avatar.style.justifyContent = 'center';
                avatar.style.fontWeight = '600';
                avatar.style.marginRight = '10px';
                const name = document.createElement('div');
                name.innerHTML = `<div style="font-weight:600;">${c.name}</div><div style="font-size:12px;color:${c.online ? '#10b981' : '#6b7280'};">${c.online ? 'Online' : 'Offline'}</div>`;
                left.appendChild(avatar);
                left.appendChild(name);
                row.appendChild(left);
                row.addEventListener('click', function(){
                    selectedRole = c.id;
                    if (adminMsgChatTitle) adminMsgChatTitle.textContent = c.name;
                    if (adminMsgChatStatus) {
                        adminMsgChatStatus.textContent = c.online ? 'Online' : 'Offline';
                        adminMsgChatStatus.style.color = c.online ? '#10b981' : '#6b7280';
                    }
                    renderAdminContacts();
                    loadAdminMessages(selectedRole);
                });
                adminMsgContactList.appendChild(row);
            });
        }
        function renderAdminChat(messages){
            if (!adminMsgChat) return;
            adminMsgChat.innerHTML = '';
            const welcome = { from:'contact', text:'Hello, how can we assist you?', time:Date.now()-600000 };
            const all = [welcome].concat(messages || []);
            all.forEach(m => {
                const bubble = document.createElement('div');
                bubble.style.padding = '10px 12px';
                bubble.style.borderRadius = '12px';
                bubble.style.maxWidth = '70%';
                bubble.style.background = (m.from === 'admin') ? '#4f46e5' : '#fff';
                bubble.style.color = (m.from === 'admin') ? '#fff' : '#111827';
                bubble.style.boxShadow = '0 1px 2px rgba(0,0,0,.06)';
                bubble.textContent = m.text || m.message || '';
                const meta = document.createElement('div');
                meta.style.fontSize = '12px';
                meta.style.color = '#6b7280';
                meta.style.marginTop = '6px';
                const dt = m.time ? new Date(m.time) : (m.created_at ? new Date(m.created_at) : new Date());
                meta.textContent = dt.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.alignItems = 'center';
                row.style.justifyContent = (m.from === 'admin') ? 'flex-end' : 'flex-start';
                const wrap = document.createElement('div');
                wrap.style.margin = '10px';
                wrap.appendChild(bubble);
                wrap.appendChild(meta);
                row.appendChild(wrap);
                adminMsgChat.appendChild(row);
            });
            adminMsgChat.scrollTop = adminMsgChat.scrollHeight;
        }
        async function loadAdminMessages(role){
            try{
                const fd = new FormData();
                fd.append('action','list_messages');
                fd.append('recipient_role', role);
                const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                if (!res.ok) { renderAdminChat([]); return; }
                const data = await res.json();
                const msgs = (data && data.success && Array.isArray(data.messages)) ? data.messages.map(m => ({ from:'admin', text:m.message, created_at:m.created_at })) : [];
                renderAdminChat(msgs);
            }catch(_){
                renderAdminChat([]);
            }
        }
        async function sendAdminMessage(){
            const text = adminMsgInput ? adminMsgInput.value.trim() : '';
            if (!text) return;
            try{
                const fd = new FormData();
                fd.append('action','send_message');
                fd.append('recipient_role', selectedRole);
                fd.append('message', text);
                const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                const data = await res.json();
                if (data && data.success) {
                    if (adminMsgInput) adminMsgInput.value = '';
                    loadAdminMessages(selectedRole);
                }
            }catch(_){}
        }
        if (adminMsgContactSearch) adminMsgContactSearch.addEventListener('input', renderAdminContacts);
        if (adminMsgSendBtn) adminMsgSendBtn.addEventListener('click', function(){ sendAdminMessage(); });
        renderAdminContacts();
        loadAdminMessages(selectedRole);
        setInterval(function(){ loadAdminMessages(selectedRole); }, 2000);
        
        const userBackBtn = document.getElementById('user-back-btn');
        if (userBackBtn) {
            userBackBtn.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }
        const userSearch = document.getElementById('user-search');
        const userTbody = document.getElementById('user-tbody');
        const userRoleButtons = document.querySelectorAll('#user-role-filters .user-role-filter');
        let userData = [];
        let selectedRoleCategory = 'ALL';
        function renderUsers(){
            if (!userTbody) return;
            const q = (userSearch && userSearch.value || '').toLowerCase();
            userTbody.innerHTML = '';
            const arr = userData.filter(u=>{
                const name = String(((u.first_name||'')+' '+(u.middle_name||'')+' '+(u.last_name||''))).toLowerCase();
                const email = String(u.email||'').toLowerCase();
                const role = String(u.role||'').toLowerCase();
                const roleMatch = selectedRoleCategory === 'ALL' ? true : (String(u.role||'').toUpperCase() === selectedRoleCategory);
                return roleMatch && (!q || name.includes(q) || email.includes(q) || role.includes(q));
            });
            if (!arr.length){
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 5;
                td.style.padding = '14px';
                td.textContent = 'No users found.';
                tr.appendChild(td);
                userTbody.appendChild(tr);
                return;
            }
            arr.forEach(u=>{
                const tr = document.createElement('tr');
                function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.style.borderBottom='1px solid #f1f5f9'; td.innerHTML=text; return td; }
                const name = (u.first_name||'')+' '+(u.middle_name||'')+' '+(u.last_name||'');
                tr.appendChild(tdWith(escapeHtml(name)));
                tr.appendChild(tdWith(escapeHtml(String(u.username||'')+' • '+String(u.email||''))));
                tr.appendChild(tdWith(escapeHtml(u.role||'USER')));
                tr.appendChild(tdWith((parseInt(u.is_verified||0)?'<span class="badge badge-active">Yes</span>':'<span class="badge badge-pending">No</span>')));
                const created = u.created_at ? new Date(u.created_at).toLocaleString() : '';
                tr.appendChild(tdWith(escapeHtml(created)));
                userTbody.appendChild(tr);
            });
        }
        function escapeHtml(s){ const div=document.createElement('div'); div.textContent=String(s||''); return div.innerHTML; }
        async function loadUsers(){
            try{
                const fd = new FormData();
                fd.append('action','user_list');
                const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                const data = await res.json();
                userData = (data && data.success) ? (data.users||[]) : [];
                const counts = { ALL: userData.length, ADMIN:0, CAPTAIN:0, SECRETARY:0, TANOD:0, USER:0 };
                userData.forEach(u=>{ const r=String(u.role||'').toUpperCase(); if (counts[r]!==undefined) counts[r]++; });
                userRoleButtons.forEach(btn=>{ const r=btn.getAttribute('data-role'); btn.textContent = r.charAt(0)+r.slice(1).toLowerCase() + (counts[r]!==undefined ? ` (${counts[r]})` : ''); if (r==='ALL') btn.textContent = `All (${counts.ALL})`; });
                renderUsers();
            }catch(_){ userData = []; renderUsers(); }
        }
        if (userSearch) userSearch.addEventListener('input', renderUsers);
        if (userRoleButtons && userRoleButtons.length){
            userRoleButtons.forEach(btn=>{
                btn.addEventListener('click', function(){
                    selectedRoleCategory = this.getAttribute('data-role');
                    userRoleButtons.forEach(b=>b.classList.remove('active'));
                    this.classList.add('active');
                    renderUsers();
                });
            });
        }
        const userCreateOpenBtn = document.getElementById('user-create-open-btn');
        const userCreateModal = document.getElementById('user-create-modal');
        const userCreateClose = document.getElementById('user-create-close');
        const userCreateCancel = document.getElementById('user-create-cancel');
        const userCreateForm = document.getElementById('user-create-form');
        const userCreateStatus = document.getElementById('user-create-status');
        function openUserCreate(){ if (userCreateModal) userCreateModal.style.display = 'flex'; }
        function closeUserCreate(){ if (userCreateModal) userCreateModal.style.display = 'none'; if (userCreateStatus) userCreateStatus.textContent=''; }
        if (userCreateOpenBtn) userCreateOpenBtn.addEventListener('click', openUserCreate);
        if (userCreateClose) userCreateClose.addEventListener('click', closeUserCreate);
        if (userCreateCancel) userCreateCancel.addEventListener('click', closeUserCreate);
        if (userCreateForm){
            userCreateForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const fd = new FormData(userCreateForm);
                fd.append('action','user_create');
                try{
                    const res = await fetch('admin_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (userCreateStatus) {
                        userCreateStatus.textContent = data && data.success ? 'Account created' : (data && data.error ? data.error : 'Failed to create');
                        userCreateStatus.style.color = data && data.success ? '#16a34a' : '#dc2626';
                    }
                    if (data && data.success){
                        closeUserCreate();
                        userCreateForm.reset();
                        loadUsers();
                    }
                }catch(_){
                    if (userCreateStatus) { userCreateStatus.textContent = 'Network error'; userCreateStatus.style.color = '#dc2626'; }
                }
            });
        }
        loadUsers();

        const registryBack = document.getElementById('registry-back');
        if (registryBack) {
            registryBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const registryTable = document.getElementById('registry-table');
        if (registryTable) {
            registryTable.addEventListener('click', function(e){
                const row = e.target.closest('.registry-row');
                if (!row) return;
                const details = document.getElementById('registry-details');
                details.style.display = 'block';
                const name = row.getAttribute('data-name');
                const email = row.getAttribute('data-email');
                const role = row.getAttribute('data-role');
                details.innerHTML = `<div style="display:flex;align-items:center;gap:16px;"><img src="../img/cpas-logo.png" alt="" style="width:44px;height:44px;border-radius:50%;object-fit:cover;"><div><div style="font-weight:600;font-size:16px;">${name}</div><div style="color:#6b7280;font-size:14px;">${email}</div><div class="badge badge-role" style="margin-top:6px;">${role}</div></div></div>`;
            });
        }

        const obsBack = document.getElementById('obs-back');
        if (obsBack) {
            obsBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const obsTable = document.getElementById('obs-table');
        if (obsTable) {
            obsTable.addEventListener('click', function(e){
                const row = e.target.closest('.obs-row');
                if (!row) return;
                const panel = document.getElementById('obs-details');
                panel.style.display = 'block';
                const dt = row.getAttribute('data-dt');
                const loc = row.getAttribute('data-loc');
                const cat = row.getAttribute('data-cat');
                const desc = row.getAttribute('data-desc');
                const status = row.getAttribute('data-status');
                panel.innerHTML = `<div><div style="font-weight:600;font-size:16px;margin-bottom:8px;">${cat}</div><div style="color:#6b7280;font-size:14px;">${dt} • ${loc}</div><div style="margin-top:10px;">${desc}</div><div style="margin-top:10px;" class="badge ${status==='Resolved'?'badge-resolved':'badge-pending'}">${status}</div></div>`;
            });
        }

        const assignBack = document.getElementById('assign-back');
        if (assignBack) {
            assignBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const assignTable = document.getElementById('assign-table');
        const savedAssignments = JSON.parse(localStorage.getItem('bwc_assignments') || '[]');
        if (assignTable) {
            savedAssignments.forEach(a => {
                const row = assignTable.querySelector(`.assign-row[data-id="${a.id}"]`);
                if (row) {
                    const zi = row.querySelector('.zone-input');
                    const si = row.querySelector('.street-input');
                    if (zi) zi.value = a.zone || '';
                    if (si) si.value = a.street || '';
                }
            });
            assignTable.addEventListener('click', function(e){
                const btn = e.target.closest('.assign-btn');
                if (!btn) return;
                const row = btn.closest('.assign-row');
                const id = row.getAttribute('data-id');
                const name = row.getAttribute('data-name');
                const status = row.getAttribute('data-status');
                const zone = row.querySelector('.zone-input').value.trim();
                const street = row.querySelector('.street-input').value.trim();
                const summary = document.getElementById('assign-details');
                summary.style.display = 'block';
                summary.innerHTML = `<div><div style="font-weight:600;font-size:16px;">${name}</div><div style="color:#6b7280;font-size:14px;">${status}</div><div style="margin-top:10px;">Zone: ${zone || '—'} | Street: ${street || '—'}</div></div>`;
                const existingIndex = savedAssignments.findIndex(x => String(x.id) === String(id));
                const payload = { id, zone, street };
                if (existingIndex >= 0) savedAssignments[existingIndex] = payload; else savedAssignments.push(payload);
                localStorage.setItem('bwc_assignments', JSON.stringify(savedAssignments));
            });
        }

        const liveBack = document.getElementById('live-back');
        if (liveBack) {
            liveBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const cctvTable = document.getElementById('cctv-table');
        const camConnections = JSON.parse(localStorage.getItem('cctv_connections') || '{}');
        const camDeviceIds = JSON.parse(localStorage.getItem('cctv_device_ids') || '{}');
        if (cctvTable) {
            Object.keys(camConnections).forEach(function(cid){
                const row = cctvTable.querySelector(`.cctv-row[data-id="${cid}"]`);
                if (row && camConnections[cid] === 'online') {
                    const badge = row.querySelector('td:nth-child(3) .badge');
                    if (badge) { badge.className = 'badge badge-active'; badge.textContent = 'Online'; }
                }
            });
            cctvTable.addEventListener('click', async function(e){
                const openBtn = e.target.closest('.open-cam-btn');
                if (openBtn) {
                    const row = openBtn.closest('.cctv-row');
                    const cid = row.getAttribute('data-id');
                    const name = row.getAttribute('data-name');
                    const deviceId = camDeviceIds[cid] || null;
                    const w = window.open('about:blank', 'camera_'+cid, 'width=1024,height=768,menubar=no,toolbar=no,location=no,status=no');
                    if (!w) { alert('Popup blocked. Allow popups for this site.'); return; }
                    const constraintsStr = deviceId && !String(deviceId).startsWith('bluetooth:') ? `{ video: { deviceId: { exact: '${deviceId}' } }, audio: false }` : `{ video: true, audio: false }`;
                    w.document.open('text/html','replace');
                    w.document.write(`<!DOCTYPE html><html><head><title>${name}</title><style>html,body{height:100%;margin:0;background:#1f2937;color:#fff;font:14px system-ui}.window{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-start}.bar{width:100%;background:#2d3748;padding:6px 10px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 1px 0 rgba(0,0,0,.3)}.bar .title{font-weight:600}.bar .sub{opacity:.7;font-size:12px}.stage{flex:1;display:flex;align-items:center;justify-content:center}#wrap{position:relative}video,canvas{max-width:100%;max-height:100%;background:#000}canvas{position:absolute;left:0;top:0}.btns{position:fixed;bottom:10px;right:10px;display:flex;gap:8px}button{background:#2563eb;border:none;color:#fff;padding:8px 12px;border-radius:8px;cursor:pointer}</style></head><body><div class=\"window\"><div class=\"bar\"><div class=\"title\">img</div><div class=\"sub\">VideoCapture(0)</div></div><div class=\"stage\"><div id=\"wrap\"><video id=\"v\" autoplay playsinline></video><canvas id=\"c\"></canvas></div></div><div class=\"btns\"><button id=\"snap\">Snapshot</button><button id=\"close\">Close</button></div></div><script>(async function(){function ls(u){return new Promise(function(r,i){var s=document.createElement('script');s.src=u;s.onload=r;s.onerror=i;document.head.appendChild(s);});}try{const cs=${constraintsStr};const st=await ((window.opener&&window.opener.navigator&&window.opener.navigator.mediaDevices)?window.opener.navigator.mediaDevices.getUserMedia(cs):navigator.mediaDevices.getUserMedia(cs));var v=document.getElementById('v');var c=document.getElementById('c');var x=c.getContext('2d');v.srcObject=st;v.muted=true;await v.play();await new Promise(function(r){v.onplaying=r});c.width=v.videoWidth||640;c.height=v.videoHeight||480;await ls('https://docs.opencv.org/4.x/opencv.js');await new Promise(function(r){if(cv&&cv['onRuntimeInitialized']){cv['onRuntimeInitialized']=r;}else{var t=setInterval(function(){if(cv&&cv['Mat']){clearInterval(t);r();}},50);}});var cl=new cv.CascadeClassifier();var xml=await (await fetch('https://raw.githubusercontent.com/opencv/opencv/master/data/haarcascades/haarcascade_frontalface_default.xml')).text();cv.FS_createDataFile('/', 'haarcascade_frontalface_default.xml', xml, true, false, false);cl.load('haarcascade_frontalface_default.xml');var src=new cv.Mat(c.height,c.width,cv.CV_8UC4);var g=new cv.Mat();var rv=new cv.RectVector();function tick(){x.drawImage(v,0,0,c.width,c.height);var im=x.getImageData(0,0,c.width,c.height);src.data.set(im.data);cv.cvtColor(src,g,cv.COLOR_RGBA2GRAY,0);cl.detectMultiScale(g,rv,1.15,4,0);x.strokeStyle='#2563eb';x.lineWidth=3;for(var i=0;i<rv.size();i++){var r=rv.get(i);x.strokeRect(r.x,r.y,r.width,r.height);}requestAnimationFrame(tick);}tick();document.getElementById('snap').onclick=function(){var a=document.createElement('a');a.href=c.toDataURL('image/png');a.download='snapshot.png';a.click();};document.getElementById('close').onclick=function(){window.close();};window.addEventListener('keydown',function(e){if(e.key==='Escape'){window.close();}});}catch(e){document.body.innerHTML='<div style=\"padding:20px;text-align:center\">'+String(e)+'</div>';}})();<\/script></body></html>`);
                    w.document.close();
                    try{ w.focus(); }catch(_){}
                    const panel = document.getElementById('live-details');
                    if (panel) {
                        panel.style.display = 'block';
                        panel.innerHTML = `<div><div style="font-weight:600;font-size:16px;">${name}</div><div style="color:#6b7280;font-size:14px;">Face detection active</div></div>`;
                    }
                    return;
                }
                const connectBtn = e.target.closest('.connect-cam-btn');
                if (connectBtn) {
                    const row = connectBtn.closest('.cctv-row');
                    const cid = row.getAttribute('data-id');
                    try {
                        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia && navigator.mediaDevices.enumerateDevices) {
                            await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
                            const all = await navigator.mediaDevices.enumerateDevices();
                            const cameras = all.filter(d => d.kind === 'videoinput');
                            let selected = cameras.find(d => d.label && !/default/i.test(d.label)) || cameras[0];
                            if (!selected && navigator.bluetooth && navigator.bluetooth.requestDevice) {
                                try { const dev = await navigator.bluetooth.requestDevice({ acceptAllDevices: true }); selected = { deviceId: 'bluetooth:'+dev.id }; } catch(_) {}
                            }
                            if (selected && selected.deviceId) {
                                camDeviceIds[cid] = selected.deviceId;
                                localStorage.setItem('cctv_device_ids', JSON.stringify(camDeviceIds));
                                camConnections[cid] = 'online';
                                localStorage.setItem('cctv_connections', JSON.stringify(camConnections));
                                const badge = row.querySelector('td:nth-child(3) .badge');
                                if (badge) { badge.className = 'badge badge-active'; badge.textContent = 'Online'; }
                            } else {
                                alert('No external or bluetooth camera found');
                            }
                        } else {
                            alert('Camera APIs not supported in this browser');
                        }
                    } catch(err) {
                        alert('Camera permission denied or unavailable');
                    }
                    return;
                }
            });
        }

        const evidenceBack = document.getElementById('evidence-back');
        if (evidenceBack) {
            evidenceBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const complaintBack = document.getElementById('complaint-back');
        if (complaintBack) {
            complaintBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const complaintTable = document.getElementById('complaint-table');
        if (complaintTable) {
            complaintTable.addEventListener('click', function(e){
                const row = e.target.closest('.complaint-row');
                if (!row) return;
                const panel = document.getElementById('complaint-details');
                panel.style.display = 'block';
                const name = row.getAttribute('data-resident');
                const issue = row.getAttribute('data-issue');
                const cat = row.getAttribute('data-cat');
                const loc = row.getAttribute('data-loc');
                const at = row.getAttribute('data-at');
                const status = row.getAttribute('data-status');
                panel.innerHTML = `<div><div style="font-weight:600;font-size:16px;">${name}</div><div style="color:#6b7280;font-size:14px;">${cat} • ${loc}</div><div style="margin-top:10px;">${issue}</div><div style="margin-top:10px;">Submitted: ${at}</div><div style="margin-top:10px;" class="badge ${status==='Resolved'?'badge-resolved':'badge-pending'}">${status}</div></div>`;
            });
        }

        const statusBack = document.getElementById('status-back');
        if (statusBack) {
            statusBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const statusTable = document.getElementById('complaint-status-table');
        if (statusTable) {
            const rows = Array.from(statusTable.querySelectorAll('.complaint-status-row'));
            function updateCounts(){
                const pending = rows.filter(r => (r.getAttribute('data-status')||'')==='Pending').length;
                const resolved = rows.filter(r => (r.getAttribute('data-status')||'')==='Resolved').length;
                const pc = document.getElementById('pending-count');
                const rc = document.getElementById('resolved-count');
                if (pc) pc.textContent = 'Pending: ' + pending;
                if (rc) rc.textContent = 'Resolved: ' + resolved;
            }
            updateCounts();

            statusTable.addEventListener('click', function(e){
                const row = e.target.closest('.complaint-status-row');
                const resolveBtn = e.target.closest('.status-resolve-btn');
                if (resolveBtn && row) {
                    const rid = row.getAttribute('data-id');
                    resolveBtn.disabled = true;
                    fetch('admin_dashboard.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'complaint_set_status', id: rid, status: 'resolved' }),
                        credentials: 'same-origin'
                    }).then(r => r.json()).then(d => {
                        if (d && d.success) {
                            row.setAttribute('data-status','Resolved');
                            const stCell = row.querySelector('td:nth-child(4)');
                            if (stCell) stCell.innerHTML = '<span class="badge badge-resolved">Resolved</span>';
                            updateCounts();
                            const onlineRow = document.querySelector(`#complaint-table .complaint-row[data-id="${rid}"]`);
                            if (onlineRow) {
                                onlineRow.setAttribute('data-status','Resolved');
                                const oc = onlineRow.querySelector('td:nth-child(6)');
                                if (oc) oc.innerHTML = '<span class="badge badge-resolved">Resolved</span>';
                            }
                            const panel = document.getElementById('status-details');
                            if (panel) {
                                const name = row.getAttribute('data-resident');
                                const issue = row.getAttribute('data-issue');
                                const at = row.getAttribute('data-at');
                                panel.style.display = 'block';
                                panel.innerHTML = `<div><div style="font-weight:600;font-size:16px;">${name}</div><div style="margin-top:10px;">${issue}</div><div style="margin-top:10px;">Submitted: ${at}</div><div style="margin-top:10px;" class="badge badge-resolved">Resolved</div></div>`;
                            }
                        } else {
                            alert('Failed to update status');
                        }
                    }).catch(()=>{ alert('Network error'); }).finally(()=>{ resolveBtn.disabled = false; });
                    return;
                }
                if (row) {
                    const panel = document.getElementById('status-details');
                    panel.style.display = 'block';
                    const name = row.getAttribute('data-resident');
                    const issue = row.getAttribute('data-issue');
                    const at = row.getAttribute('data-at');
                    const status = row.getAttribute('data-status');
                    panel.innerHTML = `<div><div style="font-weight:600;font-size:16px;">${name}</div><div style="margin-top:10px;">${issue}</div><div style="margin-top:10px;">Submitted: ${at}</div><div style="margin-top:10px;" class="badge ${status==='Resolved'?'badge-resolved':'badge-pending'}">${status}</div></div>`;
                }
            });

            const fAll = document.getElementById('filter-all');
            const fPending = document.getElementById('filter-pending');
            const fResolved = document.getElementById('filter-resolved');
            function applyFilter(kind){
                rows.forEach(r => {
                    const st = r.getAttribute('data-status');
                    r.style.display = (!kind || kind==='all' || (kind==='pending' && st==='Pending') || (kind==='resolved' && st==='Resolved')) ? '' : 'none';
                });
            }
            if (fAll) fAll.addEventListener('click', function(){ applyFilter('all'); });
            if (fPending) fPending.addEventListener('click', function(){ applyFilter('pending'); });
            if (fResolved) fResolved.addEventListener('click', function(){ applyFilter('resolved'); });
        }

        const analyticsBack = document.getElementById('analytics-back');
        if (analyticsBack) {
            analyticsBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const analyticsTable = document.getElementById('complaint-analytics-table');
        if (analyticsTable) {
            function showDetails(cat, loc){
                const panel = document.getElementById('analytics-details');
                panel.style.display = 'block';
                const items = Array.from(document.querySelectorAll('#complaint-table .complaint-row')).filter(r => r.getAttribute('data-cat')===cat && (!loc || r.getAttribute('data-loc')===loc));
                const list = items.slice(0,5).map(r => '<li>'+r.getAttribute('data-issue')+' — '+r.getAttribute('data-resident')+' ('+r.getAttribute('data-status')+')</li>').join('');
                panel.innerHTML = '<div><div style="font-weight:600;font-size:16px;">'+cat+(loc?' • '+loc:'')+'</div><div style="color:#6b7280;font-size:14px;">Occurrences: '+items.length+'</div><ul style="margin-top:10px;">'+list+'</ul></div>';
            }
            function updateSummary(){
                const rows = Array.from(analyticsTable.querySelectorAll('tbody tr'));
                let p=0,r=0; rows.forEach(tr=>{p+=parseInt(tr.children[2].textContent||'0',10)||0; r+=parseInt(tr.children[3].textContent||'0',10)||0;});
                const ap=document.getElementById('analytics-pending'); const ar=document.getElementById('analytics-resolved');
                if(ap) ap.textContent='Pending: '+p; if(ar) ar.textContent='Resolved: '+r;
            }
            function renderPie(){
                const el=document.getElementById('complaint-pie'); if(!el) return;
                const rows=Array.from(analyticsTable.querySelectorAll('tbody tr'));
                const byCat={}; rows.forEach(tr=>{const cat=tr.getAttribute('data-cat')||'General'; const reports=parseInt(tr.children[1].textContent||'0',10)||0; byCat[cat]=(byCat[cat]||0)+reports;});
                const labels=Object.keys(byCat); const data=labels.map(k=>byCat[k]);
                function drawSimple(){
                    const ctx=el.getContext('2d'); const tot=data.reduce((a,b)=>a+b,0)||1; const cx=el.width/2, cy=el.height/2, rr=Math.min(cx,cy)-2; let st=0; const cols=['#2563eb','#ef4444','#10b981','#f59e0b','#8b5cf6','#0ea5e9','#f43f5e','#22c55e'];
                    for(let i=0;i<data.length;i++){const ang=(data[i]/tot)*Math.PI*2; ctx.fillStyle=cols[i%cols.length]; ctx.beginPath(); ctx.moveTo(cx,cy); ctx.arc(cx,cy,rr,st,st+ang); ctx.closePath(); ctx.fill(); st+=ang;}
                }
                const s=document.createElement('script'); s.src='https://cdn.jsdelivr.net/npm/chart.js';
                s.onload=function(){ try{ window.complaintPie=new Chart(el,{ type:'pie', data:{ labels:labels, datasets:[{ data:data, backgroundColor:['#2563eb','#ef4444','#10b981','#f59e0b','#8b5cf6','#0ea5e9','#f43f5e','#22c55e']}] }, options:{ responsive:false, plugins:{ legend:{ position:'right' } }, animation:false, onClick:(evt,els)=>{ if(els && els.length){ const idx=els[0].index; const cat=labels[idx]; showDetails(cat,''); } } } }); }catch(e){ drawSimple(); } };
                s.onerror=function(){ drawSimple(); };
                document.head.appendChild(s);
            }
            updateSummary(); renderPie();
            analyticsTable.addEventListener('click', function(e){
                const row = e.target.closest('.analytics-row');
                if (!row) return;
                showDetails(row.getAttribute('data-cat'), row.getAttribute('data-loc'));
            });
            const legendEl=document.getElementById('complaint-legend');
            if(legendEl){
                legendEl.addEventListener('click', function(e){
                    const li=e.target.closest('li');
                    const btn=e.target.closest('.legend-view-btn');
                    if(li && btn){ showDetails(li.getAttribute('data-cat'), li.getAttribute('data-loc')); }
                });
            }
        }

        const taskBack = document.getElementById('task-back');
        if (taskBack) {
            taskBack.addEventListener('click', function(){
                document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                document.getElementById('home-section').style.display = 'block';
            });
        }

        const taskTable = document.getElementById('task-table');
        const tasksStore = JSON.parse(localStorage.getItem('volunteer_tasks') || '[]');
        if (taskTable) {
            taskTable.addEventListener('click', function(e){
                const btn = e.target.closest('.task-assign-btn');
                if (!btn) return;
                const row = btn.closest('.task-row');
                const id = row.getAttribute('data-id');
                const name = row.getAttribute('data-name');
                const role = row.getAttribute('data-role');
                const type = row.querySelector('.task-type').value;
                const date = row.querySelector('.task-date').value;
                const time = row.querySelector('.task-time').value.trim();
                const notes = row.querySelector('.task-notes').value.trim();
                const payload = { id, name, role, type, date, time, notes, created_at: new Date().toISOString() };
                tasksStore.push(payload);
                localStorage.setItem('volunteer_tasks', JSON.stringify(tasksStore));
                const panel = document.getElementById('task-details');
                panel.style.display = 'block';
                panel.innerHTML = `<div><div style=\"font-weight:600;font-size:16px;\">${name}</div><div style=\"color:#6b7280;font-size:14px;\">${role} • ${type}</div><div style=\"margin-top:10px;\">Date: ${date || '—'} • Time: ${time || '—'}</div><div style=\"margin-top:10px;\">Notes: ${notes || '—'}</div></div>`;
            });
        }

        const evidenceTable = document.getElementById('evidence-table');
        if (evidenceTable) {
            evidenceTable.addEventListener('click', function(e){
                const openBtn = e.target.closest('.evidence-open-btn');
                if (openBtn) {
                    const row = openBtn.closest('.evidence-row');
                    const url = row.getAttribute('data-url');
                    const title = row.getAttribute('data-title');
                    window.open(url || 'about:blank', 'evidence_'+Date.now(), 'noopener,width=1024,height=768');
                    const panel = document.getElementById('evidence-details');
                    if (panel) {
                        panel.style.display = 'block';
                        panel.innerHTML = `<div><div style="font-weight:600;font-size:16px;">${title}</div><div style="color:#6b7280;font-size:14px;">Opened in new window</div></div>`;
                    }
                    return;
                }
                const row = e.target.closest('.evidence-row');
                if (row) {
                    const panel = document.getElementById('evidence-details');
                    if (panel) {
                        const title = row.getAttribute('data-title');
                        const cam = row.getAttribute('data-camera');
                        const at = row.getAttribute('data-at');
                        const size = row.getAttribute('data-size');
                        panel.style.display = 'block';
                        panel.innerHTML = `<div><div style="font-weight:600;font-size:16px;">${title}</div><div style="color:#6b7280;font-size:14px;">${cam} • ${at} • ${size}</div></div>`;
                    }
                }
            });
        }
    </script>
</body>
</html>
