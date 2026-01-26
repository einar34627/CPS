<?php
session_start();
require_once '../config/db_connection.php';

function save_uploaded_file($file, $subdir){
    if (!isset($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return '';
    $type = @mime_content_type($file['tmp_name']);
    $size = @filesize($file['tmp_name']);
    if ($size !== false && $size > 50 * 1024 * 1024) return '';
    $ext = 'bin';
    if ($type === 'image/jpeg') $ext = 'jpg';
    elseif ($type === 'image/png') $ext = 'png';
    elseif ($type === 'image/webp') $ext = 'webp';
    elseif ($type === 'video/mp4') $ext = 'mp4';
    elseif ($type === 'video/ogg') $ext = 'ogg';
    elseif ($type === 'video/webm') $ext = 'webm';
    $root = dirname(__DIR__);
    $dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . str_replace(['\\','/'], DIRECTORY_SEPARATOR, $subdir);
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'r'.bin2hex(random_bytes(8)).'.'.$ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return '';
    return 'uploads/'.str_replace(['\\','/'], '/', $subdir).'/'.$name;
}


if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'join_watch') {
        $uid = $_SESSION['user_id'];
        try {
            try {
                $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'watch_group_member'");
                $exists = $chk && $chk->fetch(PDO::FETCH_ASSOC);
                if (!$exists) { $pdo->exec("ALTER TABLE users ADD COLUMN watch_group_member TINYINT(1) DEFAULT 0"); }
            } catch (Exception $e0) {}
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $currentRole = $stmt->fetchColumn();
            $newRole = is_string($currentRole) ? strtoupper($currentRole) : 'USER';
            if (strpos($newRole, 'WATCH') === false) {
                $newRole = $newRole . '_WATCH';
            }
            $upd = $pdo->prepare("UPDATE users SET role = ?, watch_group_member = 1 WHERE id = ?");
            $upd->execute([$newRole, $uid]);
            echo json_encode(['success' => true]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false]);
            exit();
        }
    } elseif ($action === 'quick_report') {
        $uid = $_SESSION['user_id'];
        $type = trim($_POST['type'] ?? '');
        $other = trim($_POST['other'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $cat = $type === 'other' && $other !== '' ? $other : ($type !== '' ? $type : 'Other');
        $photoUrl = '';
        $videoUrl = '';
        if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $photoUrl = save_uploaded_file($_FILES['photo'], 'reports/photos');
        }
        if (isset($_FILES['video']) && ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $videoUrl = save_uploaded_file($_FILES['video'], 'reports/videos');
        }
        try {
            try { $pdo->exec("CREATE TABLE IF NOT EXISTS watch_observations (id INT AUTO_INCREMENT PRIMARY KEY, observed_at DATETIME NOT NULL, location VARCHAR(255) DEFAULT NULL, category VARCHAR(100) DEFAULT 'Other', description TEXT NOT NULL, status VARCHAR(30) DEFAULT 'pending')"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE watch_observations ADD COLUMN IF NOT EXISTS photo_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE watch_observations ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
            $ins = $pdo->prepare("INSERT INTO watch_observations (observed_at, location, category, description, status, photo_url, video_url) VALUES (NOW(), ?, ?, ?, 'pending', ?, ?)");
            $ins->execute([$location, $cat, $desc, $photoUrl, $videoUrl]);
            echo json_encode(['success'=>true]);
            exit();
        } catch (Exception $e) {
            try { $pdo->exec("CREATE TABLE IF NOT EXISTS observations (id INT AUTO_INCREMENT PRIMARY KEY, observed_at DATETIME NOT NULL, location VARCHAR(255) DEFAULT NULL, category VARCHAR(100) DEFAULT 'Other', description TEXT NOT NULL, status VARCHAR(30) DEFAULT 'pending')"); } catch (Exception $e2) {}
            try { $pdo->exec("ALTER TABLE observations ADD COLUMN IF NOT EXISTS photo_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e2) {}
            try { $pdo->exec("ALTER TABLE observations ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e2) {}
            try {
                $ins2 = $pdo->prepare("INSERT INTO observations (observed_at, location, category, description, status, photo_url, video_url) VALUES (NOW(), ?, ?, ?, 'pending', ?, ?)");
                $ins2->execute([$location, $cat, $desc, $photoUrl, $videoUrl]);
                echo json_encode(['success'=>true]);
                exit();
            } catch (Exception $e3) {
                echo json_encode(['success'=>false]);
                exit();
            }
        }
    } elseif ($action === 'submit_tip') {
        $uid = $_SESSION['user_id'];
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = trim($_POST['category'] ?? 'General Information');
        $priority = trim($_POST['priority'] ?? 'Medium');
        $location = trim($_POST['location'] ?? '');
        $contact_info = trim($_POST['contact_info'] ?? '');
        $is_anonymous = isset($_POST['is_anonymous']) && $_POST['is_anonymous'] === '1' ? 1 : 0;
        if ($title === '') { $title = 'Anonymous Tip'; }
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
        } catch (Exception $e) {}
        try {
            $ins = $pdo->prepare("INSERT INTO tips (title, description, category, priority, location, contact_info, is_anonymous, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([$title, $description, $category !== '' ? $category : 'Other', $priority !== '' ? $priority : 'Medium', $location !== '' ? $location : null, $contact_info !== '' ? $contact_info : null, $is_anonymous, $uid]);
            echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success'=>false,'error'=>'Failed to submit tip']);
            exit();
        }
    } elseif ($action === 'dm_send') {
        $uid = $_SESSION['user_id'];
        $other_id = (int)($_POST['recipient_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        header('Content-Type: application/json');
        if ($other_id <= 0 || $message === '') { echo json_encode(['success'=>false, 'error'=>'Invalid parameters']); exit(); }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS direct_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                recipient_id INT NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pair (sender_id, recipient_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {}
        try {
            $ins = $pdo->prepare("INSERT INTO direct_messages (sender_id, recipient_id, message) VALUES (?, ?, ?)");
            $ins->execute([$uid, $other_id, $message]);
            echo json_encode(['success'=>true]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success'=>false, 'error'=>'Failed to send']);
            exit();
        }
    } elseif ($action === 'dm_list') {
        $uid = $_SESSION['user_id'];
        $other_id = (int)($_POST['other_id'] ?? 0);
        header('Content-Type: application/json');
        if ($other_id <= 0) { echo json_encode(['success'=>false, 'messages'=>[]]); exit(); }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS direct_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_id INT NOT NULL,
                recipient_id INT NOT NULL,
                message TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pair (sender_id, recipient_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (Exception $e) {}
        try {
            $stmt = $pdo->prepare("SELECT id, sender_id, recipient_id, message, created_at FROM direct_messages WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?) ORDER BY created_at ASC, id ASC");
            $stmt->execute([$uid, $other_id, $other_id, $uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true, 'messages'=>$rows]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success'=>false, 'messages'=>[]]);
            exit();
        }
    } elseif ($action === 'submit_complaint') {
        $uid = $_SESSION['user_id'];
        $category = trim($_POST['category'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $urgency = trim($_POST['urgency'] ?? '');
        $anonymous = isset($_POST['anonymous']) && $_POST['anonymous'] === '1' ? 1 : 0;
        $resident = '';
        try {
            $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $resident = trim(($row['first_name'] ?? '').' '.($row['middle_name'] ?? '').' '.($row['last_name'] ?? ''));
            }
        } catch (Exception $e) {}
        if ($anonymous) { $resident = ''; }
        $photoUrl = '';
        $videoUrl = '';
        if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $photoUrl = save_uploaded_file($_FILES['photo'], 'complaints/photos');
        }
        if (isset($_FILES['video']) && ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $videoUrl = save_uploaded_file($_FILES['video'], 'complaints/videos');
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS complaints (
                id INT AUTO_INCREMENT PRIMARY KEY,
                submitted_at DATETIME NOT NULL,
                user_id INT NOT NULL,
                resident VARCHAR(255) DEFAULT NULL,
                issue TEXT NOT NULL,
                category VARCHAR(100) DEFAULT 'General',
                location VARCHAR(255) DEFAULT NULL,
                status VARCHAR(30) DEFAULT 'pending',
                anonymous TINYINT(1) DEFAULT 0,
                urgency VARCHAR(20) DEFAULT NULL
            )");
        } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS photo_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
        try {
            $ins = $pdo->prepare("INSERT INTO complaints (submitted_at, user_id, resident, issue, category, location, status, anonymous, urgency, photo_url, video_url) VALUES (NOW(), ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)");
            $ins->execute([$uid, $resident, $desc, ($category !== '' ? $category : 'General'), $location, $anonymous, ($urgency !== '' ? $urgency : null), $photoUrl, $videoUrl]);
            echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]);
            exit();
        } catch (Exception $e) {
            error_log("Complaint submission error: " . $e->getMessage());
            echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
            exit();
        }
    } elseif ($action === 'apply_volunteer') {
        $uid = $_SESSION['user_id'];
        $preferred_days = trim($_POST['preferred_days'] ?? '');
        $time_slots = trim($_POST['time_slots'] ?? '');
        $night_duty = isset($_POST['night_duty']) && $_POST['night_duty'] === '1' ? 1 : 0;
        $preferred_zone = trim($_POST['preferred_zone'] ?? '');
        $max_hours = trim($_POST['max_hours'] ?? '');
        $role_prefs = trim($_POST['role_prefs'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $previous_volunteer = isset($_POST['previous_volunteer']) && $_POST['previous_volunteer'] === '1' ? 1 : 0;
        $prev_org = trim($_POST['prev_org'] ?? '');
        $years_experience = trim($_POST['years_experience'] ?? '');
        $physical_fit = isset($_POST['physical_fit']) && $_POST['physical_fit'] === '1' ? 1 : (isset($_POST['physical_fit']) && $_POST['physical_fit'] === '0' ? 0 : null);
        $medical_conditions = trim($_POST['medical_conditions'] ?? '');
        $long_period = isset($_POST['long_period']) && $_POST['long_period'] === '1' ? 1 : (isset($_POST['long_period']) && $_POST['long_period'] === '0' ? 0 : null);
        $validIdUrl = '';
        if (isset($_FILES['valid_id']) && ($_FILES['valid_id']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $validIdUrl = save_uploaded_file($_FILES['valid_id'], 'volunteers/ids');
        }
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS volunteers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                created_at DATETIME NOT NULL,
                status VARCHAR(20) DEFAULT 'pending',
                preferred_days VARCHAR(255) DEFAULT NULL,
                time_slots VARCHAR(255) DEFAULT NULL,
                night_duty TINYINT(1) DEFAULT 0,
                preferred_zone VARCHAR(255) DEFAULT NULL,
                max_hours INT DEFAULT NULL,
                role_prefs TEXT DEFAULT NULL,
                skills TEXT DEFAULT NULL,
                previous_volunteer TINYINT(1) DEFAULT 0,
                prev_org VARCHAR(255) DEFAULT NULL,
                years_experience INT DEFAULT NULL,
                physical_fit TINYINT(1) DEFAULT NULL,
                medical_conditions TEXT DEFAULT NULL,
                long_period TINYINT(1) DEFAULT NULL,
                valid_id_url VARCHAR(255) DEFAULT NULL
            )");
        } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE volunteers ADD COLUMN IF NOT EXISTS availability VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
        $availability = '';
        if ($preferred_days !== '' && $time_slots !== '') {
            $availability = $preferred_days . ' • ' . $time_slots;
        } elseif ($preferred_days !== '') {
            $availability = $preferred_days;
        } elseif ($time_slots !== '') {
            $availability = $time_slots;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO volunteers (user_id, created_at, status, preferred_days, time_slots, night_duty, preferred_zone, max_hours, role_prefs, skills, previous_volunteer, prev_org, years_experience, physical_fit, medical_conditions, long_period, valid_id_url, availability) VALUES (?, NOW(), 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $uid, $preferred_days, $time_slots, $night_duty, $preferred_zone,
                ($max_hours !== '' ? (int)$max_hours : null), $role_prefs, $skills, $previous_volunteer, $prev_org,
                ($years_experience !== '' ? (int)$years_experience : null), $physical_fit, $medical_conditions, $long_period,
                $validIdUrl, ($availability !== '' ? $availability : null)
            ]);
            echo json_encode(['success'=>true, 'id'=>$pdo->lastInsertId()]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success'=>false,'error'=>'Failed to register volunteer']);
            exit();
        }
    } elseif ($action === 'event_register') {
        $uid = $_SESSION['user_id'];
        $name = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $skills = trim($_POST['skills'] ?? '');
        $volunteer = isset($_POST['volunteer']) && $_POST['volunteer'] === '1' ? 1 : 0;
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
            )");
        } catch (Exception $e) {}
        try {
            $stmt = $pdo->prepare("INSERT INTO event_registrations (user_id, name, address, contact, email, type, skills, volunteer, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([$uid, ($name !== '' ? $name : null), ($address !== '' ? $address : null), ($contact !== '' ? $contact : null), ($email !== '' ? $email : null), ($type !== '' ? $type : null), ($skills !== '' ? $skills : null), $volunteer]);
            echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success'=>false,'error'=>'Failed to register for event']);
            exit();
        }
    } elseif ($action === 'event_feedback_submit') {
        $uid = $_SESSION['user_id'];
        $name = trim($_POST['name'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $event = trim($_POST['event'] ?? '');
        $rating = trim($_POST['rating'] ?? '');
        $comments = trim($_POST['comments'] ?? '');
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
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (Exception $e) {}
        try {
            $stmt = $pdo->prepare("INSERT INTO event_feedbacks (user_id, name, contact, email, event, rating, comments, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $uid,
                ($name !== '' ? $name : null),
                ($contact !== '' ? $contact : null),
                ($email !== '' ? $email : null),
                ($event !== '' ? $event : null),
                ($rating !== '' ? $rating : null),
                ($comments !== '' ? $comments : null)
            ]);
            echo json_encode(['success'=>true,'id'=>$pdo->lastInsertId()]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success'=>false,'error'=>'Failed to submit feedback']);
            exit();
        }
    } elseif ($action === 'complaint_list') {
        $uid = $_SESSION['user_id'];
        try {
            $stmt = $pdo->prepare("SELECT id, submitted_at, issue, location, status FROM complaints WHERE user_id = ? ORDER BY submitted_at DESC LIMIT 200");
            $stmt->execute([$uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success'=>true,'complaints'=>$rows]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success'=>false,'complaints'=>[]]);
            exit();
        }
    } elseif ($action === 'commonwealth_id_upload') {
        $uid = $_SESSION['user_id'];
        if (!isset($_FILES['photo']) || ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            echo json_encode(['success'=>false,'error'=>'no_file']);
            exit();
        }
        $name = '';
        try {
            $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name FROM users WHERE id = ?");
            $stmt->execute([$uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $name = trim(($row['first_name'] ?? '').' '.($row['middle_name'] ?? '').' '.($row['last_name'] ?? ''));
            }
        } catch (Exception $e) {}
        if ($name === '') { $name = 'user_'.$uid; }
        $safe = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
        $root = dirname(__DIR__);
        $dir = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'dataset' . DIRECTORY_SEPARATOR . $safe;
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $type = @mime_content_type($_FILES['photo']['tmp_name']);
        $ext = 'jpg';
        if ($type === 'image/png') $ext = 'png';
        elseif ($type === 'image/webp') $ext = 'webp';
        $fname = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $fname;
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
            echo json_encode(['success'=>false,'error'=>'save_failed']);
            exit();
        }
        $rel = 'scripts/dataset/'.$safe.'/'.$fname;
        $trained = false;
        try {
            $script = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'face_recognition_lbph.py';
            $cmds = [
                'python "' . $script . '" --train-only',
                'py "' . $script . '" --train-only'
            ];
            foreach ($cmds as $cmd) {
                $out = @shell_exec($cmd . ' 2>&1');
                if (is_string($out) && strpos($out, 'TRAINED') !== false) { $trained = true; break; }
            }
        } catch (Exception $e) { $trained = false; }
        echo json_encode(['success'=>true,'path'=>$rel,'person'=>$safe,'trained'=>$trained]);
        exit();
    }
}


$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, middle_name, last_name, role, avatar_url, username, contact, address, date_of_birth, email FROM users WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    $first_name = htmlspecialchars($user['first_name']);
    $middle_name = htmlspecialchars($user['middle_name']);
    $last_name = htmlspecialchars($user['last_name']);
    $role = htmlspecialchars($user['role']);
    $avatar_url = isset($user['avatar_url']) ? $user['avatar_url'] : null;
    $avatar_path = $avatar_url ? '../'.$avatar_url : '../img/rei.jfif';
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
    $username = "";
    $contact = "";
    $address = "";
    $date_of_birth = "";
    $email = "";
}

$stmt = null;

// Resolve default Tanod contact
$tanod_id = 0;
try {
    $stmtTanod = $pdo->query("SELECT id FROM users WHERE role = 'TANOD' ORDER BY id ASC LIMIT 1");
    $rowTanod = $stmtTanod ? $stmtTanod->fetch(PDO::FETCH_ASSOC) : null;
    if ($rowTanod && isset($rowTanod['id'])) { $tanod_id = (int)$rowTanod['id']; }
} catch (Exception $e) {}
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
    <style>
        .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:1000}
        .modal-card{width:90%;max-width:700px;max-height:85vh;display:flex;flex-direction:column}
        .modal-card .card-content{overflow-y:auto;max-height:85vh}
        .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
        .modal-body{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .modal-step{display:none}
        .modal-step.active{display:block}
        .modal-body .full{grid-column:1/-1}
        .modal-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:16px}
        .modal-input, .modal-select, .modal-textarea{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:8px;font-size:16px}
        .modal-card label{font-size:16px}
        .modal-card input[type="checkbox"]{transform:scale(1.4);margin-right:8px}
        .modal-textarea{min-height:100px;resize:vertical}
        body.dark-mode .modal-input, body.dark-mode .modal-select, body.dark-mode .modal-textarea{background:#1f2937;color:#e5e7eb;border-color:#374151}
        .pulse-anim{animation:pulse 1.6s ease-in-out infinite;will-change:transform,box-shadow}
        @keyframes pulse{
            0%{transform:scale(1);box-shadow:0 0 0 0 rgba(99,102,241,0.4)}
            50%{transform:scale(1.06);box-shadow:0 0 0 10px rgba(99,102,241,0)}
            100%{transform:scale(1);box-shadow:0 0 0 0 rgba(99,102,241,0)}
        }
        
        /* Profile & Security Styles */
        .readonly-field {
            padding: 12px 14px;
            background-color: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            color: #374151;
            margin-top: 5px;
            font-size: 0.95rem;
        }
        .option-list{display:flex;flex-direction:column;gap:6px}
        .option-list>div{display:flex;align-items:center;gap:6px}
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background-color: #6366f1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: #4f46e5;
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background-color: white;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-outline:hover {
            background-color: #f9fafb;
            border-color: #6366f1;
        }
        
        .btn-danger {
            background-color: #ef4444;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
            transform: translateY(-1px);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .security-item {
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 15px;
            background-color: white;
        }
        
        .security-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .security-title {
            font-weight: 600;
            color: #111827;
            font-size: 1.1rem;
        }
        
        .security-status {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .security-description {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .danger-zone {
            border: 2px solid #ef4444;
            background-color: #fef2f2;
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
        }
        
        .danger-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: #ef4444;
        }
        
        .danger-title {
            font-weight: 600;
            color: #ef4444;
            font-size: 1.2rem;
        }
        
        .danger-description {
            color: #7f1d1d;
            margin-bottom: 20px;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .security-status-card {
            background-color: #f9fafb;
            padding: 20px;
            border-radius: 8px;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-value {
            font-weight: 600;
        }
        
        .status-good {
            color: #10b981;
        }
        
        .session-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .session-icon {
            width: 40px;
            height: 40px;
            background-color: #eef2ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: #6366f1;
        }
        
        .session-info {
            flex: 1;
        }
        
        .session-name {
            font-weight: 600;
            color: #111827;
        }
        
        .session-details {
            color: #6b7280;
            font-size: 0.85rem;
        }
        
        .security-tips {
            list-style-type: none;
            padding: 0;
        }
        
        .security-tips li {
            padding: 8px 0;
            color: #4b5563;
            position: relative;
            padding-left: 20px;
        }
        
        .security-tips li:before {
            content: "✓";
            color: #10b981;
            position: absolute;
            left: 0;
        }
        
        /* Dark mode adjustments */
        body.dark-mode .readonly-field {
            background-color: #374151;
            border-color: #4b5563;
            color: #e5e7eb;
        }
        
        body.dark-mode .security-item {
            background-color: #374151;
            border-color: #4b5563;
        }
        
        body.dark-mode .security-title {
            color: #e5e7eb;
        }
        
        body.dark-mode .security-status-card {
            background-color: #374151;
        }
        
        body.dark-mode .session-item {
            background-color: #374151;
            border-color: #4b5563;
        }
        
        body.dark-mode .session-name {
            color: #e5e7eb;
        }
        
        body.dark-mode .btn-outline {
            background-color: #374151;
            border-color: #4b5563;
            color: #e5e7eb;
        }
        
        body.dark-mode .btn-outline:hover {
            background-color: #4b5563;
        }
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
                <span class="logo-text">Comumunity Policing and surveillance</span>
            </div>
            
          <!-- Menu Section -->
<div class="menu-section">
    <p class="menu-title">COMMUNITY POLICING MANAGEMENT</p>
    
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
            <span class="font-medium">Neighborhood Watch</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="fire-incident" class="submenu">
            <a href="#" class="submenu-item" id="join-watch-link">Join Watch Group</a>
            <a href="#" class="submenu-item" id="watch-schedule-link">Watch Schedule Viewing</a>
            <a href="#" class="submenu-item" id="report-suspicious-link">Report Suspicious Activity</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('volunteer')">
            <div class="icon-box icon-bg-blue">
                <i class='bx bxs-user-detail icon-blue'></i>
            </div>
            <span class="font-medium">Community Complaint Submission</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="volunteer" class="submenu">
            <a href="#" class="submenu-item" id="complaint-submit-link">Submit Complaint Form</a>
            <a href="#" class="submenu-item" id="complaint-status-link">Complaint Status Tracker</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('inventory')">
            <div class="icon-box icon-bg-green">
                <i class='bx bxs-cube icon-green'></i>
            </div>
            <span class="font-medium">Volunteer Participation and Scheduling</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="inventory" class="submenu">
            <a href="#" class="submenu-item" id="volunteer-application-link">Volunteer Application</a>
            <a href="#" class="submenu-item" id="available-duty-link">Available Duty Viewing</a>
            <a href="#" class="submenu-item" id="confirm-decline-link">Confirm / Decline Assignments</a>
            <a href="#" class="submenu-item" id="participation-history-link">Participation History</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('schedule')">
            <div class="icon-box icon-bg-purple">
                <i class='bx bxs-calendar icon-purple'></i>
            </div>
            <span class="font-medium">Community Events and Outreach</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="schedule" class="submenu">
            <a href="#" class="submenu-item" id="event-registration-link">Event Registration</a>
            <a href="#" class="submenu-item" id="event-feedback-link">Event Feedback Form</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('training')">
            <div class="icon-box icon-bg-teal">
                <i class='bx bxs-graduation icon-teal'></i>
            </div>
            <span class="font-medium">Anonymous Tip Line</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="training" class="submenu">
            <a href="#" class="submenu-item" id="anonymous-tip-link">Anonymous Tip Submission</a>
            <a href="#" class="submenu-item" id="anonymous-messages-link">Messages</a>
        </div>
    </div>
    
    <p class="menu-title" style="margin-top: 32px;">GENERAL</p>
    
    <div class="menu-items">
        <div class="menu-item" id="sidebar-settings-btn">
            <div class="icon-box icon-bg-teal">
                <i class='bx bxs-cog icon-teal'></i>
            </div>
            <span class="font-medium">Settings</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
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
                            <input type="text" placeholder="Search" class="search-input">
                            <kbd class="search-shortcut">🔥</kbd>
                        </div>
                    </div>
                    
                    <div class="header-actions">
                        <button class="theme-toggle" id="theme-toggle">
                            <i class='bx bx-moon'></i>
                            <span>Dark Mode</span>
                        </button>
                        <button class="secondary-button" id="header-quick-report-btn">Quick Report</button>
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
                        <div class="user-profile">
                             <img src="<?php echo $avatar_path; ?>" alt="User" class="user-avatar">
                            <div class="user-info" style="position:relative;">
                                <p class="user-name"><?php echo $full_name; ?></p>
                                <div id="join-success-bubble" style="display:none;position:absolute;left:0;top:calc(100% + 6px);background:#10b981;color:#fff;padding:8px 12px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.1);font-weight:600;font-size:12px;">successfully join!</div>
                                <div id="volunteer-success-bubble" style="display:none;position:absolute;left:0;top:calc(100% + 6px);background:#16a34a;color:#fff;padding:8px 12px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.1);font-weight:600;font-size:12px;">successfully submit!</div>
                                <div id="tip-success-bubble" style="display:none;position:absolute;left:0;top:calc(100% + 6px);background:#6366f1;color:#fff;padding:8px 12px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.1);font-weight:600;font-size:12px;">successfully submit a tip!</div>
                                <p class="user-email"><?php echo $role; ?></p>
                          </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- dashboard content palitan nyo nalnag ng content na gamit sa system nyo -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Community Policing and Management</h1>
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
                            <h2 class="card-title">Commonwealth ID</h2>
                            <div style="display:flex;align-items:center;justify-content:center;padding:40px 20px;">
                                <div style="text-align:center;width:100%;">
                                    <div style="font-weight:700;font-size:18px;color:#374151;letter-spacing:.5px;">Would you like to have a Commonwealth ID?</div>
                                    <button id="commonwealth-id-open-btn" style="margin-top:16px;background:linear-gradient(90deg,#a855f7,#d946ef);color:#fff;border:none;padding:12px 20px;border-radius:10px;box-shadow:0 8px 20px rgba(168,85,247,.3);cursor:pointer;">Get Now!</button>
                                </div>
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
            <div id="volunteer-application-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Volunteer Application</h1>
                        <p class="dashboard-subtitle">Apply to participate in community volunteer activities.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="volunteer-back-btn">Back to Dashboard</button>
                        <button class="primary-button" id="volunteer-now-btn">Volunteer Now!</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Why Volunteer?</h2>
                            <p style="margin-top:8px;line-height:1.6;">Support community safety initiatives, assist in events, and join patrol schedules. Submit your details to get matched with suitable activities.</p>
                        </div>
                        <div class="card">
                            <h2 class="card-title">Requirements</h2>
                            <ul style="margin-top:8px;line-height:1.6;">
                                <li>Valid contact information</li>
                                <li>Preferred availability schedule</li>
                                <li>Willingness to comply with community guidelines</li>
                            </ul>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Next Steps</h2>
                            <p style="margin-top:8px;line-height:1.6;">Click the Volunteer Now! button to fill out the application form. You will stay on this page.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="confirm-decline-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Confirm / Decline Assignments</h1>
                        <p class="dashboard-subtitle">Review and respond to your assigned volunteer tasks.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="confirm-decline-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Your Assignments</h2>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Assignment ID</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Shift</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Duty</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Location</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="confirm-decline-tbody">
                                        <tr>
                                            <td id="confirm-decline-message" colspan="7" style="padding:14px;">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Instructions</h2>
                            <p style="margin-top:8px;line-height:1.6;">If you have joined the volunteer application, your assignments will appear here. You can confirm or decline each assignment. If you are not currently joined, a message will be shown instead.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="participation-history-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Participation History</h1>
                        <p class="dashboard-subtitle">Your volunteer work records.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="participation-history-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">History</h2>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Duty</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Location</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Hours</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Outcome</th>
                                        </tr>
                                    </thead>
                                    <tbody id="participation-history-tbody">
                                        <tr>
                                            <td id="participation-history-message" colspan="5" style="padding:14px;">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Notes</h2>
                            <p style="margin-top:8px;line-height:1.6;">This list shows past volunteer activities. If you haven't joined the volunteer program, a message will be shown instead.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="available-duty-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Available Duty Viewing</h1>
                        <p class="dashboard-subtitle">Check your volunteer duty status.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="available-duty-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Duty Schedule</h2>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Shift</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Duty</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Location</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="available-duty-tbody">
                                        <tr>
                                            <td id="available-duty-message" colspan="5" style="padding:14px;">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Information</h2>
                            <p style="margin-top:8px;line-height:1.6;">This section will reflect your volunteer status. If you have joined, you will see a message to wait for your schedule. Otherwise, you will see that you are not currently in the volunteer.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="join-watch-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Join Watch Group</h1>
                        <p class="dashboard-subtitle">Become part of the neighborhood watch group.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="join-watch-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Neighborhood Watch</h2>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <tbody>
                                        <tr>
                                            <td style="padding:24px;text-align:center;">
                                                <div style="font-weight:600;font-size:18px;margin-bottom:10px;">WANNA JOIN IN WATCH GROUP?</div>
                                                <button class="primary-button pulse-anim" id="join-watch-apply-btn" style="display:block;margin:10px auto 0 auto;">Apply Now!!</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Instructions</h2>
                            <p style="margin-top:8px;line-height:1.6;">Click Apply Now!! to submit your interest. You will stay on this page.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="watch-schedule-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Watch Schedule Viewing</h1>
                        <p class="dashboard-subtitle">Your assigned patrol schedule.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="watch-schedule-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Assigned Patrol</h2>
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
                                    <tbody id="watch-schedule-tbody">
                                        <tr>
                                            <td id="watch-schedule-message" colspan="4" style="padding:14px;">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Notes</h2>
                            <p style="margin-top:8px;line-height:1.6;">If you are a member of the watch group, your patrol schedule will appear here. Otherwise, you will see a message indicating you are not currently in the watch group.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="report-suspicious-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Report Suspicious Activity</h1>
                        <p class="dashboard-subtitle">Submit a quick report for immediate attention.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="report-suspicious-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Quick Report</h2>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <tbody>
                                        <tr>
                                            <td style="padding:24px;text-align:center;">
                                                <div style="max-width:700px;margin:0 auto 10px auto;font-size:16px;line-height:1.5;">
                                                    Submit brief details of suspicious behavior or incidents for immediate review and response by barangay authorities.
                                                </div>
                                                <button class="primary-button" id="quick-report-btn" style="display:block;margin:10px auto 0 auto;">Quick Report</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Guidelines</h2>
                            <p style="margin-top:8px;line-height:1.6;">Use Quick Report for brief, time-sensitive incidents. Provide location and a short description. You will stay on this page.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="anonymous-tip-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Anonymous Tip Submission</h1>
                        <p class="dashboard-subtitle">Submit a confidential tip without leaving the dashboard.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="anonymous-tip-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Submit Anonymous Tip</h2>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <tbody>
                                        <tr>
                                            <td style="padding:24px;text-align:center;">
                                                <div style="max-width:700px;margin:0 auto 10px auto;font-size:16px;line-height:1.5;">
                                                    Share information anonymously to help maintain community safety.
                                                </div>
                                                <button class="primary-button" id="anonymous-tip-open-btn" style="display:block;margin:10px auto 0 auto;">Submit a Tip?</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Guidelines</h2>
                            <p style="margin-top:8px;line-height:1.6;">Avoid personal identifiers. Provide clear details and location if known.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="anonymous-messages-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Messages</h1>
                        <p class="dashboard-subtitle">Chat with Admin without leaving the dashboard.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="anonymous-messages-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card" style="height:100%;">
                            <h2 class="card-title">Contacts</h2>
                            <div style="padding:12px;">
                                <input id="messages-contact-search" class="modal-input" type="text" placeholder="Search contacts...">
                            </div>
                            <div id="messages-contact-list" style="padding:0 12px 12px 12px;max-height:520px;overflow-y:auto;"></div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card" style="height:100%;">
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 12px 0 12px;">
                                <div>
                                    <h2 class="card-title" id="messages-chat-title">Tanod</h2>
                                    <div id="messages-chat-status" style="font-size:14px;color:#10b981;">Online</div>
                                </div>
                                <div style="display:flex;gap:10px;color:#6b7280;">
                                    <span>🗨️</span><span>📞</span><span>⋯</span>
                                </div>
                            </div>
                            <div id="messages-chat" style="padding:12px;max-height:420px;overflow-y:auto;background:#f9fafb;border-radius:8px;margin:12px;">
                            </div>
                            <div style="display:flex;gap:8px;padding:12px;">
                                <input id="messages-input" class="modal-input" type="text" placeholder="Type a message...">
                                <button class="primary-button" id="messages-send-btn" style="min-width:80px;">Send</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="complaint-submit-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Submit Complaint Form</h1>
                        <p class="dashboard-subtitle">File a community complaint without leaving the dashboard.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="complaint-submit-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Submit Complaint</h2>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <tbody>
                                        <tr>
                                            <td style="padding:24px;text-align:center;">
                                                <div style="max-width:700px;margin:0 auto 10px auto;font-size:16px;line-height:1.5;">
                                                    Provide the complaint details to assist barangay officials in addressing community concerns.
                                                </div>
                                                <button class="primary-button" id="complaint-open-btn" style="display:block;margin:10px auto 0 auto;">Submit Complaint</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Guidelines</h2>
                            <p style="margin-top:8px;line-height:1.6;">Please include a clear subject and description. Optional: location and attachments.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="complaint-status-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Complaint Status Tracker</h1>
                        <p class="dashboard-subtitle">Track your complaints without leaving the dashboard.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="complaint-status-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Your Complaints</h2>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">ID</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date & Time</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Location</th>
                                            <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="complaint-status-tbody">
                                        <tr>
                                            <td id="complaint-status-message" colspan="5" style="padding:14px;">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Statuses</h2>
                            <p style="margin-top:8px;line-height:1.6;">Complaints progress through Pending, Under Review, and Resolved.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="event-registration-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Event Registration</h1>
                        <p class="dashboard-subtitle">Register for barangay events without leaving the dashboard.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="event-registration-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Event Registrations</h2>
                            <div style="text-align:center;margin:8px 0 12px 0;">
                                Supports community participation by allowing residents and volunteers to sign up for barangay events without visiting the barangay hall.
                            </div>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    
                                    <tbody id="event-registration-tbody">
                                    </tbody>
                                </table>
                            </div>
                            <div style="padding:12px;">
                                <button class="primary-button pulse-anim" id="event-register-now-btn" style="display:block;margin:10px auto 0 auto;">Join the Barangay Event! Register Now!</button>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Participation & Skills</h2>
                            <p style="margin-top:8px;line-height:1.6;">Select participant type and optional skills in the form.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div id="event-feedback-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Event Feedback</h1>
                        <p class="dashboard-subtitle">Share your event experience without leaving the dashboard.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="event-feedback-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Your Feedback</h2>
                            <div style="text-align:center;margin:8px 0 12px 0;">
                                How was the event? Rate your experience and share your suggestions with the team.
                            </div>
                            <div style="overflow-x:auto;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <tbody id="event-feedback-tbody">
                                    </tbody>
                                </table>
                            </div>
                            <div style="padding:12px;">
                                <button class="primary-button" id="event-feedback-open-btn" style="display:block;margin:10px auto 0 auto;">feedback?</button>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Notes</h2>
                            <p style="margin-top:8px;line-height:1.6;">Provide event name, a rating, and comments.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- PROFILE SECTION -->
            <div id="settings-profile-section" style="display:none;">
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
                                </div>
                                
                                <div class="form-group">
                                    <label>Username</label>
                                    <div class="readonly-field" id="profile-username"><?php echo $username; ?></div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <div class="readonly-field" id="profile-email"><?php echo $email; ?></div>
                                    <button class="btn-outline" id="change-email-btn" style="margin-top: 8px; padding: 8px 12px;">
                                        <i class='bx bxs-edit'></i> Change
                                    </button>
                                </div>
                                
                                <div class="form-group">
                                    <label>Contact Number</label>
                                    <input type="tel" id="profile-contact" class="modal-input" value="<?php echo $contact; ?>" placeholder="Enter contact number">
                                </div>
                                
                                <div class="form-group">
                                    <label>Date of Birth</label>
                                    <input type="date" id="profile-dob" class="modal-input" value="<?php echo $date_of_birth; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label>Address</label>
                                    <textarea id="profile-address" class="modal-textarea" rows="3"><?php echo $address; ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label>Profile Picture</label>
                                    <div style="display: flex; align-items: center; gap: 15px; margin-top: 10px;">
                                        <div class="user-avatar" id="profile-avatar" style="width: 80px; height: 80px; font-size: 2rem; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: flex; align-items: center; justify-content: center; color: white; border-radius: 50%;">
                                            <?php echo strtoupper(substr($first_name, 0, 1)); ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <input type="file" id="profile-picture" class="modal-input" accept="image/*">
                                            <small style="color: #6b7280; display: block; margin-top: 5px;">
                                                Max file size: 2MB. Allowed: JPG, PNG, GIF
                                            </small>
                                        </div>
                                    </div>
                                </div>
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
            
            <!-- SECURITY SECTION -->
            <div id="settings-security-section" style="display:none;">
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
                        <!-- Change Password -->
                        <div class="card">
                            <div class="security-item">
                                <div class="security-header">
                                    <div>
                                        <h3 class="security-title">Change Password</h3>
                                        <div class="security-status" id="password-last-changed">Last changed 3 months ago</div>
                                    </div>
                                    <button class="btn-primary" id="change-password-btn">
                                        <i class='bx bxs-key'></i> Change
                                    </button>
                                </div>
                                <p class="security-description">
                                    Ensure your account is using a long, random password to stay secure.
                                </p>
                            </div>
                            
                            <!-- Email Address -->
                            <div class="security-item">
                                <div class="security-header">
                                    <div>
                                        <h3 class="security-title">Email Address</h3>
                                        <div class="security-status"><?php echo $email; ?></div>
                                    </div>
                                    <button class="btn-outline" id="change-email-security-btn">
                                        <i class='bx bxs-edit'></i> Change
                                    </button>
                                </div>
                                <p class="security-description">
                                    Your email address is used for account notifications and password resets.
                                </p>
                            </div>
                            
                            <!-- API Access -->
                            <div class="security-item">
                                <div class="security-header">
                                    <div>
                                        <h3 class="security-title">API Access</h3>
                                        <div class="security-status">
                                            <span class="badge badge-info">No API key generated</span>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <button class="btn-outline btn-small" id="generate-api-key-btn">
                                            <i class='bx bxs-plus-circle'></i> Generate Key
                                        </button>
                                        <button class="btn-outline btn-small" id="enable-api-btn">
                                            <i class='bx bxs-power-off'></i> Enable
                                        </button>
                                    </div>
                                </div>
                                <p class="security-description">
                                    API keys allow external applications to access your data. Generate with caution.
                                </p>
                            </div>
                            
                            <!-- Two-Factor Authentication -->
                            <div class="security-item">
                                <div class="security-header">
                                    <div>
                                        <h3 class="security-title">Two-Factor Authentication</h3>
                                        <div class="security-status">
                                            <span class="badge badge-danger">Disabled</span>
                                        </div>
                                    </div>
                                    <button class="btn-outline" id="enable-2fa-btn">
                                        <i class='bx bxs-lock-alt'></i> Enable 2FA
                                    </button>
                                </div>
                                <p class="security-description">
                                    Add an extra layer of security to your account by enabling two-factor authentication.
                                </p>
                            </div>
                            
                            <!-- Danger Zone -->
                            <div class="danger-zone">
                                <div class="danger-header">
                                    <i class='bx bxs-error-circle'></i>
                                    <h3 class="danger-title">Danger Zone</h3>
                                </div>
                                <p class="danger-description">
                                    Once you delete your account, there is no going back. Please be certain.
                                </p>
                                <button class="btn-danger" id="delete-account-btn">
                                    <i class='bx bxs-trash'></i> Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Security Status</h2>
                            <div class="security-status-card">
                                <div class="status-item">
                                    <span>Password Strength:</span>
                                    <span class="status-value status-good">Strong</span>
                                </div>
                                <div class="status-item">
                                    <span>Account Activity:</span>
                                    <span class="status-value status-good">Normal</span>
                                </div>
                                <div class="status-item">
                                    <span>Login Devices:</span>
                                    <span class="status-value">1 device</span>
                                </div>
                                <div class="status-item">
                                    <span>Last Login:</span>
                                    <span class="status-value" id="last-login-time">Just now</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <h2 class="card-title">Active Sessions</h2>
                            <div class="session-item">
                                <div class="session-icon">
                                    <i class='bx bx-desktop'></i>
                                </div>
                                <div class="session-info">
                                    <p class="session-name">Chrome on Windows</p>
                                    <p class="session-details">Current session • <?php echo date('M d, Y H:i'); ?></p>
                                </div>
                                <button class="btn-outline btn-small session-end-btn">
                                    End
                                </button>
                            </div>
                        </div>
                        
                        <div class="card">
                            <h2 class="card-title">Security Tips</h2>
                            <ul class="security-tips">
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
    
    <div id="volunteer-modal" class="modal-overlay">
        <div class="card modal-card">
            <div class="card-content" style="padding:20px;">
                <div class="modal-header">
                    <h2 class="card-title">Volunteer Application Form</h2>
                    <button class="secondary-button" id="volunteer-modal-close">Close</button>
                </div>
                <form id="volunteer-form">
                    <div id="volunteer-modal-step-indicator" style="margin-bottom:8px;color:#6b7280;font-size:14px;">Page 1 of 3</div>
                    <div class="modal-body">
                        <div class="modal-step full" id="va-step-1">
                            <label for="va_fullname">Full Name</label>
                            <input id="va_fullname" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                        </div>
                        <div class="modal-step full" id="va-step-1">
                            <label for="va_username">Username</label>
                            <input id="va_username" class="modal-input" type="text" value="<?php echo $username; ?>" disabled>
                        </div>
                        <div class="modal-step full" id="va-step-1">
                            <label for="va_contact">Contact Number</label>
                            <input id="va_contact" class="modal-input" type="tel" value="<?php echo $contact; ?>" disabled>
                        </div>
                        <div class="modal-step full" id="va-step-1">
                            <label for="va_email">Email</label>
                            <input id="va_email" class="modal-input" type="email" value="<?php echo $email; ?>" disabled>
                        </div>
                        <div class="modal-step" id="va-step-1">
                            <label for="va_dob">Date of Birth</label>
                            <input id="va_dob" class="modal-input" type="date" value="<?php echo $date_of_birth; ?>" disabled>
                        </div>
                        <div class="modal-step" id="va-step-1">
                            <label for="va_age">Age</label>
                            <input id="va_age" class="modal-input" type="number" disabled>
                        </div>
                        <div class="modal-step full" id="va-step-1">
                            <label for="va_address">Address</label>
                            <input id="va_address" class="modal-input" type="text" value="<?php echo $address; ?>" disabled>
                        </div>
                        <div class="modal-step full" id="va-step-1">
                            <label for="va_nationality">Nationality</label>
                            <input id="va_nationality" class="modal-input" type="text" placeholder="Enter nationality">
                        </div>
                        <div class="modal-step" id="va-step-1">
                            <label>Sex/Gender</label>
                            <div>
                                <input type="checkbox" id="va_gender_male"><label for="va_gender_male" style="margin-left:6px;">Male</label>
                            </div>
                            <div>
                                <input type="checkbox" id="va_gender_female"><label for="va_gender_female" style="margin-left:6px;">Female</label>
                            </div>
                        </div>
                        <div class="modal-step" id="va-step-1">
                            <label>Civil Status</label>
                            <div>
                                <input type="checkbox" id="va_status_single"><label for="va_status_single" style="margin-left:6px;">Single</label>
                            </div>
                            <div>
                                <input type="checkbox" id="va_status_married"><label for="va_status_married" style="margin-left:6px;">Married</label>
                            </div>
                            <div>
                                <input type="checkbox" id="va_status_widowed"><label for="va_status_widowed" style="margin-left:6px;">Widowed</label>
                            </div>
                            <div>
                                <input type="checkbox" id="va_status_separated"><label for="va_status_separated" style="margin-left:6px;">Legally Separated</label>
                            </div>
                            <div>
                                <input type="checkbox" id="va_status_annulled"><label for="va_status_annulled" style="margin-left:6px;">Annulled</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label>Preferred Days Availability</label>
                            <div>
                                <input type="checkbox" id="va_days_weekdays"><label for="va_days_weekdays" style="margin-left:6px;">Weekdays</label>
                            </div>
                            <div id="weekday-days" style="margin-left:18px;display:none;">
                                <input type="checkbox" id="va_day_mon"><label for="va_day_mon" style="margin-left:6px;">Monday</label>
                                <input type="checkbox" id="va_day_tue" style="margin-left:14px;"><label for="va_day_tue" style="margin-left:6px;">Tuesday</label>
                                <input type="checkbox" id="va_day_wed" style="margin-left:14px;"><label for="va_day_wed" style="margin-left:6px;">Wednesday</label>
                                <input type="checkbox" id="va_day_thu" style="margin-left:14px;"><label for="va_day_thu" style="margin-left:6px;">Thursday</label>
                                <input type="checkbox" id="va_day_fri" style="margin-left:14px;"><label for="va_day_fri" style="margin-left:6px;">Friday</label>
                            </div>
                            <div>
                                <input type="checkbox" id="va_days_weekends"><label for="va_days_weekends" style="margin-left:6px;">Weekends</label>
                            </div>
                            <div id="weekend-days" style="margin-left:18px;display:none;">
                                <input type="checkbox" id="va_day_sat"><label for="va_day_sat" style="margin-left:6px;">Saturday</label>
                                <input type="checkbox" id="va_day_sun" style="margin-left:14px;"><label for="va_day_sun" style="margin-left:6px;">Sunday</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label>Preferred Time Slots</label>
                            <div>
                                <input type="checkbox" id="va_time_morning"><label for="va_time_morning" style="margin-left:6px;">Morning</label>
                                <input type="checkbox" id="va_time_afternoon" style="margin-left:14px;"><label for="va_time_afternoon" style="margin-left:6px;">Afternoon</label>
                                <input type="checkbox" id="va_time_evening" style="margin-left:14px;"><label for="va_time_evening" style="margin-left:6px;">Evening</label>
                                <input type="checkbox" id="va_time_night" style="margin-left:14px;"><label for="va_time_night" style="margin-left:6px;">Night</label>
                            </div>
                        </div>
                        <div class="modal-step" id="va-step-2">
                            <label>Willing for Night Duty</label>
                            <div>
                                <input type="checkbox" id="va_night_yes"><label for="va_night_yes" style="margin-left:6px;">Yes</label>
                            </div>
                            <div>
                                <input type="checkbox" id="va_night_no"><label for="va_night_no" style="margin-left:6px;">No</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label for="va_zone_text">Preferred Assignment Area/Zone</label>
                            <input id="va_zone_text" class="modal-input" type="text" placeholder="Enter preferred area/zone">
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label for="va_max_hours_text">Maximum Hours per Week</label>
                            <input id="va_max_hours_text" class="modal-input" type="text" placeholder="e.g., 10">
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label for="va_valid_id">Upload Valid ID (image)</label>
                            <input id="va_valid_id" class="modal-input" type="file" accept="image/*">
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>Volunteer Role Preferences</label>
                            <div class="option-list">
                                <div><input type="checkbox" id="va_role_patrol"><label for="va_role_patrol" style="margin-left:6px;">Patrol Assistance</label></div>
                                <div><input type="checkbox" id="va_role_event"><label for="va_role_event" style="margin-left:6px;">Event & Crowd Management</label></div>
                                <div><input type="checkbox" id="va_role_disaster"><label for="va_role_disaster" style="margin-left:6px;">Disaster Response Support</label></div>
                                <div><input type="checkbox" id="va_role_traffic"><label for="va_role_traffic" style="margin-left:6px;">Traffic Assistance</label></div>
                                <div><input type="checkbox" id="va_role_awareness"><label for="va_role_awareness" style="margin-left:6px;">Awareness & Outreach Activities</label></div>
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>Relevant Skills</label>
                            <div class="option-list">
                                <div><input type="checkbox" id="va_skill_firstaid"><label for="va_skill_firstaid" style="margin-left:6px;">First Aid / CPR</label></div>
                                <div><input type="checkbox" id="va_skill_safety"><label for="va_skill_safety" style="margin-left:6px;">Security / Safety Training</label></div>
                                <div><input type="checkbox" id="va_skill_communication"><label for="va_skill_communication" style="margin-left:6px;">Communication Skills</label></div>
                                <div><input type="checkbox" id="va_skill_crowd"><label for="va_skill_crowd" style="margin-left:6px;">Crowd Control</label></div>
                                <div><input type="checkbox" id="va_skill_it"><label for="va_skill_it" style="margin-left:6px;">IT / Computer Skills</label></div>
                                <div><input type="checkbox" id="va_skill_driving"><label for="va_skill_driving" style="margin-left:6px;">Driving (with license)</label></div>
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>Previous Volunteer</label>
                            <div>
                                <input type="checkbox" id="va_prev_yes"><label for="va_prev_yes" style="margin-left:6px;">Yes</label>
                            </div>
                            <div>
                                <input type="checkbox" id="va_prev_no"><label for="va_prev_no" style="margin-left:6px;">No</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label for="va_prev_org">Organization / Barangay Previously Served</label>
                            <input id="va_prev_org" class="modal-input" type="text" placeholder="Optional">
                        </div>
                        <div class="modal-step" id="va-step-3">
                            <label for="va_prev_years">Years of Experience</label>
                            <input id="va_prev_years" class="modal-input" type="text" placeholder="e.g., 2">
                        </div>
                        <div class="modal-step" id="va-step-3">
                            <label>Physical Condition</label>
                            <div>
                                <input type="checkbox" id="va_fit_yes"><label for="va_fit_yes" style="margin-left:6px;">Fit for duty: Yes</label>
                            </div>
                            <div>
                                <input type="checkbox" id="va_fit_no"><label for="va_fit_no" style="margin-left:6px;">Fit for duty: No</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label for="va_medical_cond">Medical Conditions (optional)</label>
                            <textarea id="va_medical_cond" class="modal-textarea" placeholder="Optional"></textarea>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>Ability to stand / walk for long periods</label>
                            <div>
                                <input type="checkbox" id="va_longperiod_yes"><label for="va_longperiod_yes" style="margin-left:6px;">Yes</label>
                            </div>
                            <div>
                                <input type="checkbox" id="va_longperiod_no"><label for="va_longperiod_no" style="margin-left:6px;">No</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="secondary-button" id="volunteer-modal-cancel">Cancel</button>
                        <button type="button" class="secondary-button" id="volunteer-modal-back" style="display:none;">Back</button>
                        <button type="button" class="secondary-button" id="volunteer-modal-next">Next</button>
                        <button type="submit" class="primary-button" id="volunteer-submit-btn" style="display:none;">Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="suspicious-report-modal" class="modal-overlay">
        <div class="card modal-card">
            <div class="card-content" style="padding:20px;">
                <div class="modal-header">
                    <h2 class="card-title">Quick Report Form</h2>
                    <button class="secondary-button" id="suspicious-close">Close</button>
                </div>
                <form id="suspicious-form">
                    <div class="modal-body">
                        <div class="modal-step full" id="sr-step-1">
                            <label for="incident_type">Type of Incident</label>
                            <select id="incident_type" class="modal-select">
                                <option value="suspicious_person">Suspicious Person</option>
                                <option value="theft">Theft</option>
                                <option value="suspicious_activities">Suspicious Activities</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="modal-step full" id="sr-step-1" style="display:none;" data-other-field="true">
                            <label for="incident_other">Specify Other</label>
                            <input id="incident_other" class="modal-input" type="text" placeholder="Enter incident type">
                        </div>
                        <div class="modal-step full" id="sr-step-1">
                            <label for="incident_location">Location (Barangay Zone / Street)</label>
                            <input id="incident_location" class="modal-input" type="text" placeholder="e.g., Zone 3, Market Street">
                        </div>
                        <div class="modal-step full" id="sr-step-1">
                            <label for="incident_desc">Description</label>
                            <input id="incident_desc" class="modal-input" type="text" placeholder="Short description">
                        </div>
                        <div class="modal-step" id="sr-step-1">
                            <label for="incident_photo">Upload Photo</label>
                            <input id="incident_photo" class="modal-input" type="file" accept="image/*">
                        </div>
                        <div class="modal-step" id="sr-step-1">
                            <label for="incident_video">Upload Short Video</label>
                            <input id="incident_video" class="modal-input" type="file" accept="video/*">
                        </div>
                        <div class="modal-step" id="sr-step-1">
                            <label for="incident_time">Time</label>
                            <input id="incident_time" class="modal-input" type="text" disabled>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="secondary-button" id="suspicious-cancel">Cancel</button>
                        <button type="submit" class="primary-button" id="suspicious-submit">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="complaint-modal" class="modal-overlay">
        <div class="card modal-card">
            <div class="card-content" style="padding:20px;">
                <div class="modal-header">
                    <h2 class="card-title">Complaint Form</h2>
                    <button class="secondary-button" id="complaint-close">Close</button>
                </div>
                <form id="complaint-form">
                    <div class="modal-body">
                        <div class="modal-step full" style="margin-bottom:8px;">
                            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="complaint_anonymous">Submit as Anonymous</label>
                        </div>
                        <div class="modal-step full" id="cf-step-1">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">Complaint Information</label>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="cf_fullname" style="display:block;margin-bottom:6px;">Full Name</label>
                                <input id="cf_fullname" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="cf_address" style="display:block;margin-bottom:6px;">Address</label>
                                <input id="cf_address" class="modal-input" type="text" value="<?php echo $address; ?>" disabled>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="cf_contact" style="display:block;margin-bottom:6px;">Contact Number</label>
                                <input id="cf_contact" class="modal-input" type="tel" value="<?php echo $contact; ?>" disabled>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="cf_email" style="display:block;margin-bottom:6px;">Email</label>
                                <input id="cf_email" class="modal-input" type="email" value="<?php echo $email; ?>" disabled>
                            </div>
                        </div>
                        <div class="modal-step full" id="cf-step-2">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">Complaint Details</label>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label style="display:block;margin-bottom:6px;">Type of Complaint:</label>
                                <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;">
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_type_noise">Noise Disturbance</label>
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_type_public">Public Safety Issue</label>
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_type_neighbor">Neighbor Dispute</label>
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_type_sanitation">Sanitation / Garbage</label>
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_type_parking">Illegal Parking</label>
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_type_others">Others</label>
                                </div>
                                <div id="cmp_type_other_wrap" style="display:none;margin-top:8px;">
                                    <input id="complaint_other" class="modal-input" type="text" placeholder="Specify other complaint type">
                                </div>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="complaint_time" style="display:block;margin-bottom:6px;">Date & Time of Incident</label>
                                <input id="complaint_time" class="modal-input" type="text" disabled>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="complaint_location" style="display:block;margin-bottom:6px;">Location of Incident</label>
                                <input id="complaint_location" class="modal-input" type="text" placeholder="e.g., Zone 3, Market Street">
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="complaint_description" style="display:block;margin-bottom:6px;">Description of Complaint</label>
                                <textarea id="complaint_description" class="modal-textarea" placeholder="Describe the issue"></textarea>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="complaint_photo" style="display:block;margin-bottom:6px;">Upload Photo</label>
                                <input id="complaint_photo" class="modal-input" type="file" accept="image/*">
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="complaint_video" style="display:block;margin-bottom:6px;">Upload Video</label>
                                <input id="complaint_video" class="modal-input" type="file" accept="video/*">
                            </div>
                            <div class="modal-step full">
                                <label style="font-weight:600;display:block;margin-bottom:6px;">Urgency Level</label>
                                <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;">
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_urgency_low">Low (non-urgent concern)</label>
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_urgency_medium">Medium (disturbance affecting community)</label>
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_urgency_high">High (immediate danger or emergency)</label>
                                </div>
                            </div>
                            <div class="modal-step full" style="margin-top:16px;padding-top:16px;border-top:1px solid #e5e7eb;">
                                <label style="display:flex;align-items:center;gap:8px;font-weight:500;"><input type="checkbox" id="complaint_consent" required> I confirm that the information provided is true and accurate to the best of my knowledge</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="cf-step-3">
                            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="complaint_consent">I confirm that the information provided is true and accurate.</label>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="secondary-button" id="complaint-cancel">Cancel</button>
                        <button type="submit" class="primary-button" id="complaint-submit-btn">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="event-registration-modal" class="modal-overlay">
        <div class="card modal-card">
            <div class="card-content" style="padding:20px;">
                <div class="modal-header">
                    <h2 class="card-title">Event Registration Form</h2>
                    <button class="secondary-button" id="event-modal-close">Close</button>
                </div>
                <form id="event-registration-form">
                    <div class="modal-body">
                        <div class="modal-step full" id="er-step-1">
                            <label for="er_fullname">Full Name</label>
                            <input id="er_fullname" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                        </div>
                        <div class="modal-step full" id="er-step-1">
                            <label for="er_address">Address</label>
                            <input id="er_address" class="modal-input" type="text" value="<?php echo $address; ?>" disabled>
                        </div>
                        <div class="modal-step full" id="er-step-1">
                            <label for="er_contact">Contact Number</label>
                            <input id="er_contact" class="modal-input" type="tel" value="<?php echo $contact; ?>" disabled>
                        </div>
                        <div class="modal-step full" id="er-step-1">
                            <label for="er_email">Email</label>
                            <input id="er_email" class="modal-input" type="email" value="<?php echo $email; ?>" disabled>
                        </div>
                        <div class="modal-step" id="er-step-2">
                            <label><input type="checkbox" id="er_register_volunteer">Register as Volunteer (Optional)</label>
                        </div>
                        <div class="modal-step full" id="er-step-2">
                            <label>Type of Participants:</label>
                            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="er_type_attendee">Attendee</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="er_type_volunteer">Event Volunteer</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="er_type_resource">Resource Assistant</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="er-step-2">
                            <label>Special Skills:</label>
                            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="er_skill_firstaid">First Aid</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="er_skill_crowd">Crowd Control</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="er_skill_logistics">Logistics Support</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="er_skill_docs">Documentation</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="secondary-button" id="event-modal-cancel">Cancel</button>
                        <button type="submit" class="primary-button" id="event-submit-btn">Submit Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="event-feedback-modal" class="modal-overlay">
        <div class="card modal-card">
            <div class="card-content" style="padding:20px;">
                <div class="modal-header">
                    <h2 class="card-title">Event Feedback Form</h2>
                    <button class="secondary-button" id="event-feedback-close">Close</button>
                </div>
                <form id="event-feedback-form">
                    <div class="modal-body">
                        <div class="modal-step full" id="ef-step-1">
                            <label for="ef_name">Name (optional): </label>
                            <input id="ef_name" class="modal-input" type="text" placeholder="Optional">
                        </div>
                        <div class="modal-step full" id="ef-step-1">
                            <label>Role: </label>
                            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_role_resident">Resident</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_role_volunteer">Volunteer</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_role_youth">Youth Leader</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="ef-step-2">
                            <div style="font-weight:600;margin:8px 0 4px 0;">Event Evaluation</div>
                            <div style="color:#6b7280;margin-bottom:10px;">(Scale: 1 – Very Poor | 5 – Excellent)</div>
                        </div>
                        <div class="modal-step full" id="ef-step-2">
                            <label>1. Overall satisfaction with the event</label>
                            <div style="display:flex;gap:16px;margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q1_1">1</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q1_2">2</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q1_3">3</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q1_4">4</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q1_5">5</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="ef-step-2">
                            <label>2. Clarity of the topics discussed</label>
                            <div style="display:flex;gap:16px;margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q2_1">1</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q2_2">2</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q2_3">3</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q2_4">4</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q2_5">5</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="ef-step-2">
                            <label>3. Speaker / Facilitator effectiveness</label>
                            <div style="display:flex;gap:16px;margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q3_1">1</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q3_2">2</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q3_3">3</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q3_4">4</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q3_5">5</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="ef-step-2">
                            <label>4. Usefulness of the information provided</label>
                            <div style="display:flex;gap:16px;margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q4_1">1</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q4_2">2</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q4_3">3</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q4_4">4</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q4_5">5</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="ef-step-2">
                            <label>5. Event organization and flow</label>
                            <div style="display:flex;gap:16px;margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q5_1">1</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q5_2">2</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q5_3">3</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q5_4">4</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ef_q5_5">5</label>
                            </div>
                        </div>
                        <div class="modal-step full" id="ef-step-2">
                            <label for="ef_comments">Comments and Suggestions</label>
                            <textarea id="ef_comments" class="modal-textarea" placeholder="Share your suggestions"></textarea>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="secondary-button" id="event-feedback-cancel">Cancel</button>
                        <button type="submit" class="primary-button" id="event-feedback-submit">Submit Feedback</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="commonwealth-id-modal" class="modal-overlay" style="display:none;">
        <div class="card modal-card">
            <div class="card-content" style="padding:20px;">
                <div class="modal-header">
                    <h2 class="card-title">Get Commonwealth ID</h2>
                    <button class="secondary-button" id="commonwealth-id-close">Close</button>
                </div>
                <form id="commonwealth-id-form">
                    <div class="modal-body">
                        <div class="modal-step full" style="margin-bottom:8px;">
                            <label>Full Name</label>
                            <input class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                        </div>
                        <div class="modal-step full" style="margin-bottom:8px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label>Username</label>
                                <input class="modal-input" type="text" value="<?php echo $username; ?>" disabled>
                            </div>
                            <div>
                                <label>Contact</label>
                                <input class="modal-input" type="tel" value="<?php echo $contact; ?>" disabled>
                            </div>
                        </div>
                        <div class="modal-step full" style="margin-bottom:8px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label>Email</label>
                                <input class="modal-input" type="email" value="<?php echo $email; ?>" disabled>
                            </div>
                            <div>
                                <label>Date of Birth</label>
                                <input class="modal-input" type="date" value="<?php echo $date_of_birth; ?>" disabled>
                            </div>
                        </div>
                        <div class="modal-step full" style="margin-bottom:8px;">
                            <label>Address</label>
                            <input class="modal-input" type="text" value="<?php echo $address; ?>" disabled>
                        </div>
                        <div class="modal-step full" style="margin-bottom:8px;">
                            <input id="commonwealth-photo" class="modal-input" type="file" accept="image/*">
                            <div style="margin-top:12px;padding:12px;border-radius:12px;background:linear-gradient(180deg,#FFFFC5,#FFFFFF);color:#111;">
                                Note: the image you uploaded to get a commonwealth ID will be used for facial recognition for the security of our area but getting an ID is not mandatory, thank you very much for your understanding!
                            </div>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="secondary-button" id="commonwealth-id-cancel">Cancel</button>
                        <button type="button" class="secondary-button" id="commonwealth-id-submit">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="anonymous-tip-modal" class="modal-overlay">
        <div class="card modal-card">
            <div class="card-content" style="padding:20px;">
                <div class="modal-header">
                    <h2 class="card-title">Anonymous Tip Submission</h2>
                    <button class="secondary-button" id="anonymous-tip-close">Close</button>
                </div>
                <form id="anonymous-tip-form">
                    <div class="modal-body">
                        <div class="modal-step full">
                            <label for="tip_subject">Subject (optional)</label>
                            <input id="tip_subject" class="modal-input" type="text" placeholder="Optional subject">
                        </div>
                        <div class="modal-step full">
                            <label for="tip_description">Tip Details</label>
                            <textarea id="tip_description" class="modal-textarea" placeholder="Describe the situation" required></textarea>
                        </div>
                        <div class="modal-step full">
                            <label for="tip_location">Location (optional)</label>
                            <input id="tip_location" class="modal-input" type="text" placeholder="e.g., Street or landmark">
                        </div>
                        <div class="modal-step full">
                            <label for="tip_photo">Upload Photo (optional)</label>
                            <input id="tip_photo" class="modal-input" type="file" accept="image/*">
                        </div>
                        <div class="modal-step full">
                            <label for="tip_video">Upload Video (optional)</label>
                            <input id="tip_video" class="modal-input" type="file" accept="video/*">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="secondary-button" id="anonymous-tip-cancel">Cancel</button>
                        <button type="submit" class="primary-button" id="anonymous-tip-submit">Submit Tip</button>
                    </div>
                </form>
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
        
        const sidebarSettingsBtn = document.getElementById('sidebar-settings-btn');
        const sidebarSettingsSubmenu = document.getElementById('sidebar-settings-submenu');
        if (sidebarSettingsBtn && sidebarSettingsSubmenu) {
            function closeSidebarSettings() {
                sidebarSettingsSubmenu.classList.remove('active');
                sidebarSettingsBtn.setAttribute('aria-expanded', 'false');
            }
            function openSidebarSettings() {
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
            const settingsLinks = document.querySelectorAll('#sidebar-settings-submenu .submenu-item');
            settingsLinks.forEach(function(link){
                link.addEventListener('click', function(){
                    closeSidebarSettings();
                });
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
        
        const volunteerLink = document.getElementById('volunteer-application-link');
        const volunteerSection = document.getElementById('volunteer-application-section');
        const dashboardSection = document.querySelector('.dashboard-content');
        const availableDutyLink = document.getElementById('available-duty-link');
        const availableDutySection = document.getElementById('available-duty-section');
        const availableDutyBackBtn = document.getElementById('available-duty-back-btn');
        const availableDutyMessage = document.getElementById('available-duty-message');
        const confirmDeclineLink = document.getElementById('confirm-decline-link');
        const confirmDeclineSection = document.getElementById('confirm-decline-section');
        const confirmDeclineBackBtn = document.getElementById('confirm-decline-back-btn');
        const confirmDeclineTbody = document.getElementById('confirm-decline-tbody');
        const confirmDeclineMessage = document.getElementById('confirm-decline-message');
        const participationHistoryLink = document.getElementById('participation-history-link');
        const participationHistorySection = document.getElementById('participation-history-section');
        const participationHistoryBackBtn = document.getElementById('participation-history-back-btn');
        const participationHistoryTbody = document.getElementById('participation-history-tbody');
        const participationHistoryMessage = document.getElementById('participation-history-message');
        const volunteerBackBtn = document.getElementById('volunteer-back-btn');
        const volunteerNowBtn = document.getElementById('volunteer-now-btn');
        const volunteerModal = document.getElementById('volunteer-modal');
        const volunteerModalClose = document.getElementById('volunteer-modal-close');
        const volunteerModalCancel = document.getElementById('volunteer-modal-cancel');
        const volunteerForm = document.getElementById('volunteer-form');
        const joinWatchLink = document.getElementById('join-watch-link');
        const joinWatchSection = document.getElementById('join-watch-section');
        const joinWatchBackBtn = document.getElementById('join-watch-back-btn');
        const joinWatchApplyBtn = document.getElementById('join-watch-apply-btn');
        const joinSuccessBubble = document.getElementById('join-success-bubble');
        const volunteerSubmitBubble = document.getElementById('volunteer-success-bubble');
        const watchScheduleLink = document.getElementById('watch-schedule-link');
        const watchScheduleSection = document.getElementById('watch-schedule-section');
        const watchScheduleBackBtn = document.getElementById('watch-schedule-back-btn');
        const watchScheduleTbody = document.getElementById('watch-schedule-tbody');
        const watchScheduleMessage = document.getElementById('watch-schedule-message');
        const reportSuspiciousLink = document.getElementById('report-suspicious-link');
        const reportSuspiciousSection = document.getElementById('report-suspicious-section');
        const reportSuspiciousBackBtn = document.getElementById('report-suspicious-back-btn');
        const quickReportBtn = document.getElementById('quick-report-btn');
        const headerQuickReportBtn = document.getElementById('header-quick-report-btn');
        const suspiciousReportModal = document.getElementById('suspicious-report-modal');
        const suspiciousClose = document.getElementById('suspicious-close');
        const suspiciousCancel = document.getElementById('suspicious-cancel');
        const suspiciousForm = document.getElementById('suspicious-form');
        const incidentTypeSelect = document.getElementById('incident_type');
        const incidentOtherField = document.querySelector('[data-other-field="true"]');
        const incidentTimeInput = document.getElementById('incident_time');
        const complaintSubmitLink = document.getElementById('complaint-submit-link');
        const complaintSubmitSection = document.getElementById('complaint-submit-section');
        const complaintSubmitBackBtn = document.getElementById('complaint-submit-back-btn');
        const complaintOpenBtn = document.getElementById('complaint-open-btn');
        const complaintStatusLink = document.getElementById('complaint-status-link');
        const complaintStatusSection = document.getElementById('complaint-status-section');
        const complaintStatusBackBtn = document.getElementById('complaint-status-back-btn');
        const complaintStatusTbody = document.getElementById('complaint-status-tbody');
        const complaintStatusMessage = document.getElementById('complaint-status-message');
        const complaintModal = document.getElementById('complaint-modal');
        const complaintClose = document.getElementById('complaint-close');
        const complaintCancel = document.getElementById('complaint-cancel');
        const complaintForm = document.getElementById('complaint-form');
        const eventRegistrationLink = document.getElementById('event-registration-link');
        const eventRegistrationSection = document.getElementById('event-registration-section');
        const eventRegistrationBackBtn = document.getElementById('event-registration-back-btn');
        const eventRegistrationTbody = document.getElementById('event-registration-tbody');
        const eventRegistrationMessage = document.getElementById('event-registration-message');
        const eventRegisterNowBtn = document.getElementById('event-register-now-btn');
        const eventRegistrationModal = document.getElementById('event-registration-modal');
        const eventModalClose = document.getElementById('event-modal-close');
        const eventModalCancel = document.getElementById('event-modal-cancel');
        const eventRegistrationForm = document.getElementById('event-registration-form');
        const eventFeedbackLink = document.getElementById('event-feedback-link');
        const eventFeedbackSection = document.getElementById('event-feedback-section');
        const eventFeedbackBackBtn = document.getElementById('event-feedback-back-btn');
        const eventFeedbackTbody = document.getElementById('event-feedback-tbody');
        const eventFeedbackMessage = document.getElementById('event-feedback-message');
        const eventFeedbackOpenBtn = document.getElementById('event-feedback-open-btn');
        const eventFeedbackModal = document.getElementById('event-feedback-modal');
        const eventFeedbackClose = document.getElementById('event-feedback-close');
        const eventFeedbackCancel = document.getElementById('event-feedback-cancel');
        const eventFeedbackForm = document.getElementById('event-feedback-form');
        const anonymousTipLink = document.getElementById('anonymous-tip-link');
        const anonymousTipSection = document.getElementById('anonymous-tip-section');
        const anonymousTipBackBtn = document.getElementById('anonymous-tip-back-btn');
        const anonymousTipOpenBtn = document.getElementById('anonymous-tip-open-btn');
        const anonymousTipModal = document.getElementById('anonymous-tip-modal');
        const anonymousTipClose = document.getElementById('anonymous-tip-close');
        const anonymousTipCancel = document.getElementById('anonymous-tip-cancel');
        const anonymousTipForm = document.getElementById('anonymous-tip-form');
        const anonymousMessagesLink = document.getElementById('anonymous-messages-link');
        const anonymousMessagesSection = document.getElementById('anonymous-messages-section');
        const anonymousMessagesBackBtn = document.getElementById('anonymous-messages-back-btn');
        const messagesContactSearch = document.getElementById('messages-contact-search');
        const messagesContactList = document.getElementById('messages-contact-list');
        const messagesChat = document.getElementById('messages-chat');
        const messagesInput = document.getElementById('messages-input');
        const messagesSendBtn = document.getElementById('messages-send-btn');
        const messagesChatTitle = document.getElementById('messages-chat-title');
        const messagesChatStatus = document.getElementById('messages-chat-status');
        const tipSuccessBubble = document.getElementById('tip-success-bubble');
        const currentUserId = <?php echo (int)$user_id; ?>;
        const dmTanodId = <?php echo (int)$tanod_id; ?>;
        const commonwealthOpenBtn = document.getElementById('commonwealth-id-open-btn');
        const commonwealthModal = document.getElementById('commonwealth-id-modal');
        const commonwealthClose = document.getElementById('commonwealth-id-close');
        const commonwealthCancel = document.getElementById('commonwealth-id-cancel');
        const commonwealthSubmit = document.getElementById('commonwealth-id-submit');
        const commonwealthPhoto = document.getElementById('commonwealth-photo');
        
        // New variables for Profile & Security
        const settingsProfileSection = document.getElementById('settings-profile-section');
        const settingsSecuritySection = document.getElementById('settings-security-section');
        const sidebarSettingsProfileLink = document.getElementById('sidebar-settings-profile-link');
        const sidebarSettingsSecurityLink = document.getElementById('sidebar-settings-security-link');
        const profileBackBtn = document.getElementById('profile-back-btn');
        const securityBackBtn = document.getElementById('security-back-btn');
        const profileSaveBtn = document.getElementById('profile-save-btn');
        const changeEmailBtn = document.getElementById('change-email-btn');
        const changeEmailSecurityBtn = document.getElementById('change-email-security-btn');
        const changePasswordBtn = document.getElementById('change-password-btn');
        const generateApiKeyBtn = document.getElementById('generate-api-key-btn');
        const enableApiBtn = document.getElementById('enable-api-btn');
        const enable2faBtn = document.getElementById('enable-2fa-btn');
        const deleteAccountBtn = document.getElementById('delete-account-btn');
        const exportDataBtn = document.getElementById('export-data-btn');
        const deactivateAccountBtn = document.getElementById('deactivate-account-btn');
        const profilePictureInput = document.getElementById('profile-picture');
        const profileAvatar = document.getElementById('profile-avatar');
        const sessionEndBtn = document.querySelector('.session-end-btn');
        
        let selectedContactId = String(dmTanodId || 0);
        const anonContacts = [{id:String(dmTanodId || 0), name:'Tanod', online:true}];
        
        function hideSubmoduleSections(){
            if (volunteerSection) volunteerSection.style.display = 'none';
            if (availableDutySection) availableDutySection.style.display = 'none';
            if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
            if (participationHistorySection) participationHistorySection.style.display = 'none';
            if (joinWatchSection) joinWatchSection.style.display = 'none';
            if (watchScheduleSection) watchScheduleSection.style.display = 'none';
            if (reportSuspiciousSection) reportSuspiciousSection.style.display = 'none';
            if (complaintSubmitSection) complaintSubmitSection.style.display = 'none';
            if (complaintStatusSection) complaintStatusSection.style.display = 'none';
            if (eventRegistrationSection) eventRegistrationSection.style.display = 'none';
            if (eventFeedbackSection) eventFeedbackSection.style.display = 'none';
            if (anonymousTipSection) anonymousTipSection.style.display = 'none';
            if (anonymousMessagesSection) anonymousMessagesSection.style.display = 'none';
            // Hide Profile & Security sections
            if (settingsProfileSection) settingsProfileSection.style.display = 'none';
            if (settingsSecuritySection) settingsSecuritySection.style.display = 'none';
        }
        function showVolunteerSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (volunteerSection) volunteerSection.style.display = 'block';
        }
        function showJoinWatchSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (joinWatchSection) joinWatchSection.style.display = 'block';
        }
        function showReportSuspiciousSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (reportSuspiciousSection) reportSuspiciousSection.style.display = 'block';
        }
        function showComplaintSubmitSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (complaintSubmitSection) complaintSubmitSection.style.display = 'block';
        }
        function showAnonymousTipSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (anonymousTipSection) anonymousTipSection.style.display = 'block';
        }
        function showAnonymousMessagesSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (anonymousMessagesSection) anonymousMessagesSection.style.display = 'block';
            renderAnonContacts();
            renderAnonChat();
        }
        async function renderComplaintStatus(){
            if (!complaintStatusTbody) return;
            complaintStatusTbody.innerHTML = '';
            try{
                const fd = new FormData();
                fd.append('action','complaint_list');
                const res = await fetch('user_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                const data = await res.json();
                const items = (data && data.success && Array.isArray(data.complaints)) ? data.complaints : [];
                if (!items.length){
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.colSpan = 5;
                    td.style.padding = '14px';
                    td.textContent = 'No complaints submitted yet.';
                    tr.appendChild(td);
                    complaintStatusTbody.appendChild(tr);
                    return;
                }
                items.forEach(c=>{
                    const tr = document.createElement('tr');
                    function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                    tr.appendChild(tdWith(c.id || '—'));
                    tr.appendChild(tdWith(c.issue || '—'));
                    const dt = c.submitted_at ? new Date(c.submitted_at).toLocaleString() : '—';
                    tr.appendChild(tdWith(dt));
                    tr.appendChild(tdWith(c.location || '—'));
                    tr.appendChild(tdWith((String(c.status||'').toLowerCase()==='resolved') ? 'Resolved' : 'Pending'));
                    complaintStatusTbody.appendChild(tr);
                });
            }catch(_){
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 5;
                td.style.padding = '14px';
                td.textContent = 'Failed to load complaints.';
                tr.appendChild(td);
                complaintStatusTbody.appendChild(tr);
            }
        }
        function showComplaintStatusSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (complaintStatusSection) complaintStatusSection.style.display = 'block';
            renderComplaintStatus();
        }
        function openEventRegistrationModal(){
            if (eventRegistrationModal) eventRegistrationModal.style.display = 'flex';
            setupExclusive(['er_type_attendee','er_type_volunteer','er_type_resource']);
            document.querySelectorAll('#event-registration-modal .modal-step').forEach(el=>el.classList.add('active'));
        }
        function closeEventRegistrationModal(){
            if (eventRegistrationModal) eventRegistrationModal.style.display = 'none';
        }
        function renderEventRegistrations(){
            if (!eventRegistrationTbody) return;
            eventRegistrationTbody.innerHTML = '';
            let items = [];
            try { items = JSON.parse(localStorage.getItem('event_registrations') || '[]'); } catch(e){ items = []; }
            if (!items || items.length === 0) return;
            items.forEach(r=>{
                const tr = document.createElement('tr');
                function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                tr.appendChild(tdWith(r.id || '—'));
                tr.appendChild(tdWith(r.name || '—'));
                tr.appendChild(tdWith(r.contact || '—'));
                tr.appendChild(tdWith(r.type || '—'));
                tr.appendChild(tdWith((r.skills && r.skills.length) ? r.skills.join(', ') : '—'));
                tr.appendChild(tdWith(r.volunteer ? 'Yes' : 'No'));
                eventRegistrationTbody.appendChild(tr);
            });
        }
        function showEventRegistrationSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (eventRegistrationSection) eventRegistrationSection.style.display = 'block';
            renderEventRegistrations();
        }
        function openEventFeedbackModal(){
            if (eventFeedbackModal) eventFeedbackModal.style.display = 'flex';
            document.querySelectorAll('#event-feedback-modal .modal-step').forEach(el=>el.classList.add('active'));
            setupExclusive(['ef_rate_excellent','ef_rate_good','ef_rate_fair','ef_rate_poor']);
        }
        function closeEventFeedbackModal(){
            if (eventFeedbackModal) eventFeedbackModal.style.display = 'none';
        }
        function renderEventFeedbacks(){
            if (!eventFeedbackTbody) return;
            eventFeedbackTbody.innerHTML = '';
            let items = [];
            try { items = JSON.parse(localStorage.getItem('event_feedbacks') || '[]'); } catch(e){ items = []; }
            if (!items || items.length === 0) return;
            items.forEach(f=>{
                const tr = document.createElement('tr');
                function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                tr.appendChild(tdWith(f.id || '—'));
                tr.appendChild(tdWith(f.name || '—'));
                tr.appendChild(tdWith(f.contact || '—'));
                tr.appendChild(tdWith(f.event || '—'));
                tr.appendChild(tdWith(f.rating || '—'));
                tr.appendChild(tdWith(f.comments || '—'));
                eventFeedbackTbody.appendChild(tr);
            });
        }
        function showEventFeedbackSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (eventFeedbackSection) eventFeedbackSection.style.display = 'block';
            renderEventFeedbacks();
        }
        function openCommonwealthIdModal(){
            if (commonwealthModal) commonwealthModal.style.display = 'flex';
            document.querySelectorAll('#commonwealth-id-modal .modal-step').forEach(el=>el.classList.add('active'));
        }
        function closeCommonwealthIdModal(){
            if (commonwealthModal) commonwealthModal.style.display = 'none';
            if (commonwealthPhoto) commonwealthPhoto.value = '';
        }
        function renderWatchSchedule(){
            const joined = localStorage.getItem('watch_group_joined') === 'true';
            if (!watchScheduleTbody) return;
            watchScheduleTbody.innerHTML = '';
            if (!joined){
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 4;
                td.style.padding = '14px';
                td.textContent = 'you are not currently in the watch group';
                tr.appendChild(td);
                watchScheduleTbody.appendChild(tr);
                return;
            }
            const sampleWatch = [
                {date:'2026-01-18', shift:'Evening', area:'Zone 2', status:'Assigned'},
                {date:'2026-01-21', shift:'Morning', area:'Barangay Hall', status:'Assigned'},
                {date:'2026-01-25', shift:'Night', area:'Market Street', status:'Assigned'}
            ];
            sampleWatch.forEach(w=>{
                const tr = document.createElement('tr');
                function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                tr.appendChild(tdWith(w.date));
                tr.appendChild(tdWith(w.shift));
                tr.appendChild(tdWith(w.area));
                tr.appendChild(tdWith(w.status));
                watchScheduleTbody.appendChild(tr);
            });
        }
        function showWatchScheduleSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (watchScheduleSection) watchScheduleSection.style.display = 'block';
            renderWatchSchedule();
        }
        function openSuspiciousModal(){
            if (suspiciousReportModal) suspiciousReportModal.style.display = 'flex';
            document.querySelectorAll('#suspicious-report-modal .modal-step').forEach(el=>el.classList.add('active'));
            if (incidentTimeInput){
                const now = new Date();
                incidentTimeInput.value = now.toLocaleString();
            }
            if (incidentTypeSelect && incidentOtherField){
                incidentOtherField.style.display = incidentTypeSelect.value === 'other' ? 'block' : 'none';
            }
        }
        function closeSuspiciousModal(){
            if (suspiciousReportModal) suspiciousReportModal.style.display = 'none';
        }
        function openComplaintModal(){
            if (complaintModal) complaintModal.style.display = 'flex';
            document.querySelectorAll('#complaint-modal .modal-step').forEach(el=>el.classList.add('active'));
            const t = document.getElementById('complaint_time');
            if (t){
                const now = new Date();
                const pad = n=>String(n).padStart(2,'0');
                const val = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
                t.value = val;
            }
            const others = document.getElementById('cmp_type_others');
            const otherWrap = document.getElementById('cmp_type_other_wrap');
            if (others && otherWrap){
                otherWrap.style.display = others.checked ? 'block' : 'none';
                others.addEventListener('change', ()=>{ otherWrap.style.display = others.checked ? 'block' : 'none'; });
            }
            setupExclusive(['cmp_urgency_low','cmp_urgency_medium','cmp_urgency_high']);
        }
        function closeComplaintModal(){
            if (complaintModal) complaintModal.style.display = 'none';
        }
        function openAnonymousTipModal(){
            if (anonymousTipModal) anonymousTipModal.style.display = 'flex';
            document.querySelectorAll('#anonymous-tip-modal .modal-step').forEach(el=>el.classList.add('active'));
        }
        function closeAnonymousTipModal(){
            if (anonymousTipModal) anonymousTipModal.style.display = 'none';
        }
        if (commonwealthOpenBtn) commonwealthOpenBtn.addEventListener('click', openCommonwealthIdModal);
        if (commonwealthClose) commonwealthClose.addEventListener('click', closeCommonwealthIdModal);
        if (commonwealthCancel) commonwealthCancel.addEventListener('click', closeCommonwealthIdModal);
        if (commonwealthSubmit) commonwealthSubmit.addEventListener('click', async function(){
            if (!commonwealthPhoto || !commonwealthPhoto.files || commonwealthPhoto.files.length === 0){
                alert('Please select an image.');
                return;
            }
            if (commonwealthPhoto.files.length > 1){
                alert('Please upload only one image.');
                return;
            }
            const file = commonwealthPhoto.files[0];
            if (!file.type || !file.type.startsWith('image/')){
                alert('Please select an image file.');
                return;
            }
            try{
                const fd = new FormData();
                fd.append('action','commonwealth_id_upload');
                fd.append('photo', file);
                const res = await fetch('user_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                const data = await res.json();
                if (data && data.success){
                    const msg = data.trained ? 'Thank you! Your photo has been uploaded and the recognizer was updated.' : 'Thank you! Your photo has been uploaded.';
                    alert(msg);
                    closeCommonwealthIdModal();
                }else{
                    alert('Upload failed.');
                }
            }catch(_){
                alert('Upload failed.');
            }
        });
        async function loadDmMessages(){
            try{
                const fd = new FormData();
                fd.append('action','dm_list');
                fd.append('other_id', selectedContactId);
                const res = await fetch('user_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                const data = await res.json();
                const rows = (data && data.success && Array.isArray(data.messages)) ? data.messages : [];
                return rows.map(r => ({
                    id: r.id,
                    from: (parseInt(r.sender_id,10) === currentUserId) ? 'user' : 'tanod',
                    text: r.message,
                    time: new Date(r.created_at).getTime()
                }));
            }catch(_){
                return [];
            }
        }
        function renderAnonContacts(){
            if (!messagesContactList) return;
            messagesContactList.innerHTML = '';
            const q = (messagesContactSearch && messagesContactSearch.value || '').toLowerCase();
            anonContacts.filter(c=>!q || c.name.toLowerCase().includes(q)).forEach(c=>{
                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.alignItems = 'center';
                row.style.justifyContent = 'space-between';
                row.style.padding = '10px';
                row.style.borderRadius = '8px';
                row.style.cursor = 'pointer';
                row.style.marginBottom = '6px';
                row.style.background = selectedContactId === c.id ? '#eef2ff' : '#fff';
                const left = document.createElement('div');
                left.style.display = 'flex';
                left.style.alignItems = 'center';
                const avatar = document.createElement('div');
                avatar.textContent = c.name.charAt(0).toUpperCase();
                avatar.style.width = '32px';
                avatar.style.height = '32px';
                avatar.style.borderRadius = '50%';
                avatar.style.display = 'flex';
                avatar.style.alignItems = 'center';
                avatar.style.justifyContent = 'center';
                avatar.style.background = '#6366f1';
                avatar.style.color = '#fff';
                avatar.style.marginRight = '10px';
                const name = document.createElement('div');
                name.innerHTML = '<div style="font-weight:600;">'+c.name+'</div><div style="font-size:12px;color:'+(c.online?'#10b981':'#6b7280')+';">'+(c.online?'Online':'Offline')+'</div>';
                left.appendChild(avatar);
                left.appendChild(name);
                row.appendChild(left);
                row.addEventListener('click', ()=>{
                    selectedContactId = c.id;
                    if (messagesChatTitle) messagesChatTitle.textContent = c.name;
                    if (messagesChatStatus) messagesChatStatus.textContent = c.online ? 'Online' : 'Offline';
                    if (messagesChatStatus) messagesChatStatus.style.color = c.online ? '#10b981' : '#6b7280';
                    renderAnonContacts();
                    renderAnonChat();
                });
                messagesContactList.appendChild(row);
            });
        }
        async function renderAnonChat(){
            if (!messagesChat) return;
            messagesChat.innerHTML = '';
            const msgs = await loadDmMessages();
            msgs.forEach(m=>{
                const wrap = document.createElement('div');
                wrap.style.display = 'flex';
                wrap.style.justifyContent = m.from === 'user' ? 'flex-end' : 'flex-start';
                const bubble = document.createElement('div');
                bubble.textContent = m.text;
                bubble.style.maxWidth = '70%';
                bubble.style.padding = '10px 12px';
                bubble.style.borderRadius = '12px';
                bubble.style.margin = '8px';
                bubble.style.background = m.from === 'user' ? '#6366f1' : '#e5e7eb';
                bubble.style.color = m.from === 'user' ? '#fff' : '#111827';
                const meta = document.createElement('div');
                meta.style.fontSize = '12px';
                meta.style.color = '#6b7280';
                meta.style.margin = '0 12px';
                meta.textContent = new Date(m.time).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.alignItems = 'center';
                row.style.justifyContent = m.from === 'user' ? 'flex-end' : 'flex-start';
                row.appendChild(bubble);
                row.appendChild(meta);
                wrap.appendChild(row);
                messagesChat.appendChild(wrap);
            });
            messagesChat.scrollTop = messagesChat.scrollHeight;
        }
        function sendAnonMessage(){
            const text = messagesInput ? messagesInput.value.trim() : '';
            if (!text) return;
            const fd = new FormData();
            fd.append('action','dm_send');
            fd.append('recipient_id', selectedContactId);
            fd.append('message', text);
            fetch('user_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' })
                .then(r=>r.json())
                .then(d=>{
                    if (d && d.success) {
                        if (messagesInput) messagesInput.value = '';
                        renderAnonChat();
                    } else {
                        alert('Failed to send message.');
                    }
                })
                .catch(()=>{ alert('Failed to send message.'); });
        }
        function showDashboard(){
            hideSubmoduleSections();
            if (dashboardSection) dashboardSection.style.display = 'block';
        }
        function showAvailableDutySection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (availableDutySection) availableDutySection.style.display = 'block';
            const joined = localStorage.getItem('volunteer_joined') === 'true';
            if (availableDutyMessage){
                availableDutyMessage.textContent = joined ? 'wait for your schedule' : 'you are not currently in the volunteer';
            }
        }
        function renderAssignments(){
            const joined = localStorage.getItem('volunteer_joined') === 'true';
            if (!confirmDeclineTbody) return;
            confirmDeclineTbody.innerHTML = '';
            if (!joined){
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 7;
                td.style.padding = '14px';
                td.textContent = 'you are not currently in the volunteer';
                tr.appendChild(td);
                confirmDeclineTbody.appendChild(tr);
                return;
            }
            const sampleAssignments = [
                {id:'A-1001', date:'2026-01-18', shift:'Evening', duty:'Event Assistance', location:'Community Center'},
                {id:'A-1002', date:'2026-01-21', shift:'Morning', duty:'Traffic Duty', location:'Market Street'},
                {id:'A-1003', date:'2026-01-25', shift:'Night', duty:'Patrol Support', location:'Zone 3'}
            ];
            sampleAssignments.forEach(a=>{
                const statusKey = 'assignmentStatus_'+a.id;
                const saved = localStorage.getItem(statusKey) || 'Pending';
                const tr = document.createElement('tr');
                function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                tr.appendChild(tdWith(a.id));
                tr.appendChild(tdWith(a.date));
                tr.appendChild(tdWith(a.shift));
                tr.appendChild(tdWith(a.duty));
                tr.appendChild(tdWith(a.location));
                const statusTd = tdWith(saved);
                statusTd.dataset.assignmentId = a.id;
                statusTd.dataset.statusCell = 'true';
                tr.appendChild(statusTd);
                const actionTd = document.createElement('td');
                actionTd.style.padding = '10px';
                const confirmBtn = document.createElement('button');
                confirmBtn.className = 'primary-button assign-confirm-btn';
                confirmBtn.textContent = 'Confirm';
                confirmBtn.dataset.assignmentId = a.id;
                const declineBtn = document.createElement('button');
                declineBtn.className = 'secondary-button assign-decline-btn';
                declineBtn.textContent = 'Decline';
                declineBtn.style.marginLeft = '8px';
                declineBtn.dataset.assignmentId = a.id;
                actionTd.appendChild(confirmBtn);
                actionTd.appendChild(declineBtn);
                tr.appendChild(actionTd);
                confirmDeclineTbody.appendChild(tr);
            });
        }
        function showConfirmDeclineSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (confirmDeclineSection) confirmDeclineSection.style.display = 'block';
            renderAssignments();
        }
        function renderParticipationHistory(){
            if (!participationHistoryTbody) return;
            participationHistoryTbody.innerHTML = '';
            const joined = localStorage.getItem('volunteer_joined') === 'true';
            if (!joined){
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 5;
                td.style.padding = '14px';
                td.textContent = 'you are not currently in the volunteer';
                tr.appendChild(td);
                participationHistoryTbody.appendChild(tr);
                return;
            }
            const sampleHistory = [
                {date:'2025-11-12', duty:'Community Outreach', location:'Barangay Hall', hours:'3.5', outcome:'Completed'},
                {date:'2025-12-03', duty:'Event Assistance', location:'Town Plaza', hours:'4.0', outcome:'Completed'},
                {date:'2026-01-05', duty:'Traffic Duty', location:'Market Street', hours:'2.0', outcome:'Completed'}
            ];
            sampleHistory.forEach(h=>{
                const tr = document.createElement('tr');
                function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                tr.appendChild(tdWith(h.date));
                tr.appendChild(tdWith(h.duty));
                tr.appendChild(tdWith(h.location));
                tr.appendChild(tdWith(h.hours));
                tr.appendChild(tdWith(h.outcome));
                participationHistoryTbody.appendChild(tr);
            });
        }
        function showParticipationHistorySection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (participationHistorySection) participationHistorySection.style.display = 'block';
            renderParticipationHistory();
        }
        function openVolunteerModal(){
            if (volunteerModal) volunteerModal.style.display = 'flex';
            initVolunteerForm();
        }
        function closeVolunteerModal(){
            if (volunteerModal) volunteerModal.style.display = 'none';
        }
        
        // PROFILE & SECURITY FUNCTIONS
        function showSettingsProfileSection() {
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (settingsProfileSection) settingsProfileSection.style.display = 'block';
            
            // Set timestamps
            const now = new Date();
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            const createdEl = document.getElementById('profile-created');
            const updatedEl = document.getElementById('profile-updated');
            if (createdEl) createdEl.textContent = now.toLocaleDateString('en-US', options);
            if (updatedEl) updatedEl.textContent = now.toLocaleDateString('en-US', options);
        }
        
        function showSettingsSecuritySection() {
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (settingsSecuritySection) settingsSecuritySection.style.display = 'block';
            
            // Set last login time
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const lastLoginEl = document.getElementById('last-login-time');
            if (lastLoginEl) lastLoginEl.textContent = timeString + ' today';
        }
        
        // PROFILE PICTURE PREVIEW
        if (profilePictureInput && profileAvatar) {
            profilePictureInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profileAvatar.style.backgroundImage = `url(${e.target.result})`;
                        profileAvatar.style.backgroundSize = 'cover';
                        profileAvatar.style.backgroundPosition = 'center';
                        profileAvatar.textContent = '';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // EVENT LISTENERS
        if (volunteerLink){
            volunteerLink.addEventListener('click', function(e){
                e.preventDefault();
                showVolunteerSection();
            });
        }
        if (availableDutyLink){
            availableDutyLink.addEventListener('click', function(e){
                e.preventDefault();
                showAvailableDutySection();
            });
        }
        if (reportSuspiciousLink){
            reportSuspiciousLink.addEventListener('click', function(e){
                e.preventDefault();
                showReportSuspiciousSection();
            });
        }
        if (complaintSubmitLink){
            complaintSubmitLink.addEventListener('click', function(e){
                e.preventDefault();
                showComplaintSubmitSection();
            });
        }
        if (complaintStatusLink){
            complaintStatusLink.addEventListener('click', function(e){
                e.preventDefault();
                showComplaintStatusSection();
            });
        }
        if (anonymousMessagesLink){
            anonymousMessagesLink.addEventListener('click', function(e){
                e.preventDefault();
                showAnonymousMessagesSection();
            });
        }
        if (anonymousTipLink){
            anonymousTipLink.addEventListener('click', function(e){
                e.preventDefault();
                showAnonymousTipSection();
            });
        }
        if (confirmDeclineLink){
            confirmDeclineLink.addEventListener('click', function(e){
                e.preventDefault();
                showConfirmDeclineSection();
            });
        }
        if (participationHistoryLink){
            participationHistoryLink.addEventListener('click', function(e){
                e.preventDefault();
                showParticipationHistorySection();
            });
        }
        if (sidebarSettingsProfileLink){
            sidebarSettingsProfileLink.addEventListener('click', function(e){
                e.preventDefault();
                showSettingsProfileSection();
            });
        }
        if (sidebarSettingsSecurityLink){
            sidebarSettingsSecurityLink.addEventListener('click', function(e){
                e.preventDefault();
                showSettingsSecuritySection();
            });
        }
        if (volunteerBackBtn){
            volunteerBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (confirmDeclineBackBtn){
            confirmDeclineBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (participationHistoryBackBtn){
            participationHistoryBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (availableDutyBackBtn){
            availableDutyBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (reportSuspiciousBackBtn){
            reportSuspiciousBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (complaintSubmitBackBtn){
            complaintSubmitBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (complaintStatusBackBtn){
            complaintStatusBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (profileBackBtn){
            profileBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (securityBackBtn){
            securityBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (volunteerNowBtn){
            volunteerNowBtn.addEventListener('click', function(e){
                e.preventDefault();
                openVolunteerModal();
            });
        }
        if (volunteerModalClose){
            volunteerModalClose.addEventListener('click', function(e){
                e.preventDefault();
                closeVolunteerModal();
            });
        }
        if (volunteerModalCancel){
            volunteerModalCancel.addEventListener('click', function(e){
                e.preventDefault();
                closeVolunteerModal();
            });
        }
        if (quickReportBtn){
            quickReportBtn.addEventListener('click', function(e){
                e.preventDefault();
                openSuspiciousModal();
            });
        }
        if (headerQuickReportBtn){
            headerQuickReportBtn.addEventListener('click', function(e){
                e.preventDefault();
                showReportSuspiciousSection();
                const rs = document.getElementById('report-suspicious-section');
                if (rs) rs.scrollIntoView({behavior:'smooth'});
                openSuspiciousModal();
            });
        }
        if (suspiciousClose){
            suspiciousClose.addEventListener('click', function(e){
                e.preventDefault();
                closeSuspiciousModal();
            });
        }
        if (suspiciousCancel){
            suspiciousCancel.addEventListener('click', function(e){
                e.preventDefault();
                closeSuspiciousModal();
            });
        }
        if (complaintOpenBtn){
            complaintOpenBtn.addEventListener('click', function(e){
                e.preventDefault();
                openComplaintModal();
            });
        }
        if (complaintClose){
            complaintClose.addEventListener('click', function(e){
                e.preventDefault();
                closeComplaintModal();
            });
        }
        if (complaintCancel){
            complaintCancel.addEventListener('click', function(e){
                e.preventDefault();
                closeComplaintModal();
            });
        }
        if (anonymousMessagesBackBtn){
            anonymousMessagesBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (anonymousTipOpenBtn){
            anonymousTipOpenBtn.addEventListener('click', function(e){
                e.preventDefault();
                openAnonymousTipModal();
            });
        }
        if (anonymousTipClose){
            anonymousTipClose.addEventListener('click', function(e){
                e.preventDefault();
                closeAnonymousTipModal();
            });
        }
        if (anonymousTipCancel){
            anonymousTipCancel.addEventListener('click', function(e){
                e.preventDefault();
                closeAnonymousTipModal();
            });
        }
        if (volunteerForm){
            volunteerForm.addEventListener('submit', async function(e){
                e.preventDefault();
                function checked(id){ const el=document.getElementById(id); return !!(el && el.checked); }
                function collect(list){ return list.filter(checked).map(id=>id.replace(/^va_(role|skill|time|day)_/,'').replace(/^va_/,'')).join(','); }
                const preferredDays = [
                    checked('va_days_weekdays') ? 'Weekdays' : '',
                    checked('va_days_weekends') ? 'Weekends' : ''
                ].filter(Boolean).join(',');
                const timeSlots = [
                    checked('va_time_morning') ? 'Morning' : '',
                    checked('va_time_afternoon') ? 'Afternoon' : '',
                    checked('va_time_evening') ? 'Evening' : '',
                    checked('va_time_night') ? 'Night' : ''
                ].filter(Boolean).join(',');
                const fd = new FormData();
                fd.append('action','apply_volunteer');
                fd.append('preferred_days', preferredDays);
                fd.append('time_slots', timeSlots);
                fd.append('night_duty', checked('va_night_yes') ? '1' : (checked('va_night_no') ? '0' : ''));
                fd.append('preferred_zone', (document.getElementById('va_zone_text')?.value || '').trim());
                fd.append('max_hours', (document.getElementById('va_max_hours_text')?.value || '').trim());
                const rolePrefs = collect(['va_role_patrol','va_role_event','va_role_disaster','va_role_traffic','va_role_awareness']);
                fd.append('role_prefs', rolePrefs);
                const skills = collect(['va_skill_firstaid','va_skill_safety','va_skill_communication','va_skill_crowd','va_skill_it','va_skill_driving']);
                fd.append('skills', skills);
                fd.append('previous_volunteer', checked('va_prev_yes') ? '1' : (checked('va_prev_no') ? '0' : ''));
                fd.append('prev_org', (document.getElementById('va_prev_org')?.value || '').trim());
                fd.append('years_experience', (document.getElementById('va_prev_years')?.value || '').trim());
                fd.append('physical_fit', checked('va_fit_yes') ? '1' : (checked('va_fit_no') ? '0' : ''));
                fd.append('medical_conditions', (document.getElementById('va_medical_cond')?.value || '').trim());
                fd.append('long_period', checked('va_longperiod_yes') ? '1' : (checked('va_longperiod_no') ? '0' : ''));
                const validIdEl = document.getElementById('va_valid_id');
                if (validIdEl && validIdEl.files && validIdEl.files[0]) {
                    fd.append('valid_id', validIdEl.files[0]);
                }
                try {
                    const res = await fetch('user_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json().catch(()=>({success:true}));
                    if (data && data.success) {
                        localStorage.setItem('volunteer_joined','true');
                        if (volunteerSubmitBubble){
                            volunteerSubmitBubble.style.display = 'block';
                            setTimeout(function(){ volunteerSubmitBubble.style.display = 'none'; }, 3000);
                        }
                        closeVolunteerModal();
                    } else {
                        alert('Failed to submit volunteer application');
                    }
                } catch (err) {
                    alert('Network error submitting application');
                }
            });
        }
        if (joinWatchLink){
            joinWatchLink.addEventListener('click', function(e){
                e.preventDefault();
                showJoinWatchSection();
            });
        }
        if (watchScheduleLink){
            watchScheduleLink.addEventListener('click', function(e){
                e.preventDefault();
                showWatchScheduleSection();
            });
        }
        if (joinWatchBackBtn){
            joinWatchBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (watchScheduleBackBtn){
            watchScheduleBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (joinWatchApplyBtn){
            joinWatchApplyBtn.addEventListener('click', async function(e){
                e.preventDefault();
                try {
                    const res = await fetch('user_dashboard.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'join_watch' }),
                        credentials: 'same-origin'
                    });
                    await res.json().catch(()=>({success:true}));
                } catch (_) {}
                localStorage.setItem('watch_group_joined','true');
                if (joinSuccessBubble){
                    joinSuccessBubble.style.display = 'block';
                    setTimeout(function(){ joinSuccessBubble.style.display = 'none'; }, 3000);
                }
                if (joinWatchApplyBtn){
                    joinWatchApplyBtn.disabled = true;
                    joinWatchApplyBtn.textContent = 'Joined';
                }
            });
        }
        if (messagesContactSearch){
            messagesContactSearch.addEventListener('input', function(){
                renderAnonContacts();
            });
        }
        if (messagesSendBtn){
            messagesSendBtn.addEventListener('click', function(e){
                e.preventDefault();
                sendAnonMessage();
            });
        }
        // Initial render and real-time polling
        renderAnonContacts();
        renderAnonChat();
        setInterval(()=>{ renderAnonChat(); }, 2000);
        if (suspiciousForm){
            if (incidentTypeSelect && incidentOtherField){
                incidentTypeSelect.addEventListener('change', function(){
                    incidentOtherField.style.display = incidentTypeSelect.value === 'other' ? 'block' : 'none';
                });
            }
            suspiciousForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const fd = new FormData();
                fd.append('action','quick_report');
                fd.append('type', document.getElementById('incident_type')?.value || '');
                fd.append('other', document.getElementById('incident_other')?.value || '');
                fd.append('location', document.getElementById('incident_location')?.value || '');
                fd.append('description', document.getElementById('incident_desc')?.value || '');
                const p = document.getElementById('incident_photo')?.files?.[0];
                const v = document.getElementById('incident_video')?.files?.[0];
                if (p) fd.append('photo', p);
                if (v) fd.append('video', v);
                try{
                    const res = await fetch('user_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    await res.json().catch(()=>({success:true}));
                }catch(_){}
                closeSuspiciousModal();
            });
        }
        if (complaintForm){
            complaintForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const submitBtn = document.getElementById('complaint-submit-btn');
                const types = [];
                if (document.getElementById('cmp_type_noise')?.checked) types.push('Noise Disturbance');
                if (document.getElementById('cmp_type_public')?.checked) types.push('Public Safety Issue');
                if (document.getElementById('cmp_type_neighbor')?.checked) types.push('Neighbor Dispute');
                if (document.getElementById('cmp_type_sanitation')?.checked) types.push('Sanitation / Garbage');
                if (document.getElementById('cmp_type_parking')?.checked) types.push('Illegal Parking');
                const otherChecked = !!document.getElementById('cmp_type_others')?.checked;
                const otherText = document.getElementById('complaint_other')?.value || '';
                if (otherChecked && otherText) types.push('Other: ' + otherText);
                const category = types.length ? types.join(', ') : 'General';
                const locEl = document.getElementById('complaint_location');
                const location = locEl ? locEl.value : '';
                const descEl = document.getElementById('complaint_description');
                const description = descEl ? descEl.value : '';
                const anonEl = document.getElementById('complaint_anonymous');
                const anonymous = anonEl ? (!!anonEl.checked ? '1' : '0') : '0';
                const urgLow = document.getElementById('cmp_urgency_low')?.checked;
                const urgMed = document.getElementById('cmp_urgency_medium')?.checked;
                const urgHigh = document.getElementById('cmp_urgency_high')?.checked;
                const urgency = urgHigh ? 'High' : (urgMed ? 'Medium' : (urgLow ? 'Low' : ''));
                const consent = !!document.getElementById('complaint_consent')?.checked;
                if (!consent) { alert('Please confirm that you agree to the terms by checking the consent checkbox'); return; }
                const fd = new FormData();
                fd.append('action','submit_complaint');
                fd.append('category', category);
                fd.append('location', location);
                fd.append('description', description);
                fd.append('anonymous', anonymous);
                fd.append('urgency', urgency);
                const p = document.getElementById('complaint_photo')?.files?.[0];
                const v = document.getElementById('complaint_video')?.files?.[0];
                
                // Validate file sizes before uploading
                if (p && p.size > 10 * 1024 * 1024) { // 10MB limit for photos
                    alert('Photo file size must be less than 10MB');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit'; }
                    return;
                }
                if (v && v.size > 50 * 1024 * 1024) { // 50MB limit for videos
                    alert('Video file size must be less than 50MB');
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit'; }
                    return;
                }
                
                if (p) fd.append('photo', p);
                if (v) fd.append('video', v);
                try{
                    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting...'; }
                    const res = await fetch('user_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (data && data.success) { 
                        alert('Complaint submitted successfully'); 
                    } else {
                        const errorMsg = data && data.error ? `Server error: ${data.error}` : 'Failed to submit complaint. Please try again.';
                        alert(errorMsg);
                        console.error('Complaint submission failed:', data);
                    }
                }catch(error){
                    alert('Error submitting complaint. Please check your connection and try again.');
                    console.error('Complaint submission error:', error);
                }
                closeComplaintModal();
                renderComplaintStatus();
                if (typeof showComplaintStatusSection === 'function') { showComplaintStatusSection(); }
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit'; }
            });
        }
        if (eventRegistrationLink){
            eventRegistrationLink.addEventListener('click', function(e){
                e.preventDefault();
                showEventRegistrationSection();
            });
        }
        if (eventRegistrationBackBtn){
            eventRegistrationBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (eventRegisterNowBtn){
            eventRegisterNowBtn.addEventListener('click', function(e){
                e.preventDefault();
                openEventRegistrationModal();
            });
        }
        if (eventModalClose){
            eventModalClose.addEventListener('click', function(e){
                e.preventDefault();
                closeEventRegistrationModal();
            });
        }
        if (eventModalCancel){
            eventModalCancel.addEventListener('click', function(e){
                e.preventDefault();
                closeEventRegistrationModal();
            });
        }
        if (eventRegistrationForm){
            eventRegistrationForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const nameEl = document.getElementById('er_fullname');
                const addrEl = document.getElementById('er_address');
                const contactEl = document.getElementById('er_contact');
                const emailEl = document.getElementById('er_email');
                const type = document.getElementById('er_type_attendee')?.checked ? 'Attendee' :
                             document.getElementById('er_type_volunteer')?.checked ? 'Event Volunteer' :
                             document.getElementById('er_type_resource')?.checked ? 'Resource Assistant' : '';
                const skills = [];
                if (document.getElementById('er_skill_firstaid')?.checked) skills.push('First Aid');
                if (document.getElementById('er_skill_crowd')?.checked) skills.push('Crowd Control');
                if (document.getElementById('er_skill_logistics')?.checked) skills.push('Logistics Support');
                if (document.getElementById('er_skill_docs')?.checked) skills.push('Documentation');
                const volunteer = !!document.getElementById('er_register_volunteer')?.checked;
                const id = 'E-' + Date.now();
                const record = {
                    id,
                    name: nameEl ? nameEl.value : '',
                    address: addrEl ? addrEl.value : '',
                    contact: contactEl ? contactEl.value : '',
                    email: emailEl ? emailEl.value : '',
                    type,
                    skills,
                    volunteer
                };
                try {
                    const res = await fetch('user_dashboard.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'event_register',
                            name: record.name,
                            address: record.address,
                            contact: record.contact,
                            email: record.email,
                            type: record.type,
                            skills: record.skills.join(','),
                            volunteer: record.volunteer ? '1' : '0'
                        }),
                        credentials: 'same-origin'
                    });
                    await res.json().catch(()=>({success:true}));
                } catch (_) {}
                let items = [];
                try { items = JSON.parse(localStorage.getItem('event_registrations') || '[]'); } catch(e){ items = []; }
                items.push(record);
                localStorage.setItem('event_registrations', JSON.stringify(items));
                closeEventRegistrationModal();
                renderEventRegistrations();
            });
        }
        if (anonymousTipForm){
            anonymousTipForm.addEventListener('submit', function(e){
                e.preventDefault();
                const subjectEl = document.getElementById('tip_subject');
                const descEl = document.getElementById('tip_description');
                const locEl = document.getElementById('tip_location');
                const formData = new FormData();
                formData.append('action','submit_tip');
                formData.append('title', subjectEl ? subjectEl.value : 'Anonymous Tip');
                formData.append('description', descEl ? descEl.value : '');
                formData.append('category','General Information');
                formData.append('priority','Medium');
                formData.append('location', locEl ? locEl.value : '');
                formData.append('contact_info','');
                formData.append('is_anonymous','0');
                fetch(window.location.href, { method:'POST', body: formData })
                    .then(r=>r.json())
                    .then(d=>{
                        if (d && d.success) {
                            if (tipSuccessBubble) {
                                tipSuccessBubble.style.display = 'block';
                                setTimeout(()=>{ tipSuccessBubble.style.display = 'none'; }, 4000);
                            }
                            closeAnonymousTipModal();
                        } else {
                            alert('Failed to submit tip. Please try again.');
                        }
                    })
                    .catch(()=>{ alert('Failed to submit tip. Please try again.'); });
            });
        }
        if (eventFeedbackLink){
            eventFeedbackLink.addEventListener('click', function(e){
                e.preventDefault();
                showEventFeedbackSection();
            });
        }
        if (eventFeedbackBackBtn){
            eventFeedbackBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        if (eventFeedbackOpenBtn){
            eventFeedbackOpenBtn.addEventListener('click', function(e){
                e.preventDefault();
                openEventFeedbackModal();
            });
        }
        if (eventFeedbackClose){
            eventFeedbackClose.addEventListener('click', function(e){
                e.preventDefault();
                closeEventFeedbackModal();
            });
        }
        if (eventFeedbackCancel){
            eventFeedbackCancel.addEventListener('click', function(e){
                e.preventDefault();
                closeEventFeedbackModal();
            });
        }
        if (eventFeedbackForm){
            eventFeedbackForm.addEventListener('submit', function(e){
                e.preventDefault();
                const nameEl = document.getElementById('ef_fullname') || document.getElementById('ef_name');
                const contactEl = document.getElementById('ef_contact');
                const emailEl = document.getElementById('ef_email');
                const eventEl = document.getElementById('ef_event');
                let rating = '';
                if (document.getElementById('ef_rate_excellent')?.checked) rating = 'Excellent';
                else if (document.getElementById('ef_rate_good')?.checked) rating = 'Good';
                else if (document.getElementById('ef_rate_fair')?.checked) rating = 'Fair';
                else if (document.getElementById('ef_rate_poor')?.checked) rating = 'Poor';
                else if (document.getElementById('ef_q1_5')?.checked) rating = 'Excellent';
                else if (document.getElementById('ef_q1_4')?.checked) rating = 'Good';
                else if (document.getElementById('ef_q1_3')?.checked) rating = 'Fair';
                else if (document.getElementById('ef_q1_2')?.checked) rating = 'Poor';
                else if (document.getElementById('ef_q1_1')?.checked) rating = 'Very Poor';
                const commentsEl = document.getElementById('ef_comments');
                const id = 'F-' + Date.now();
                const record = {
                    id,
                    name: nameEl ? nameEl.value : '',
                    contact: contactEl ? contactEl.value : '',
                    email: emailEl ? emailEl.value : '',
                    event: eventEl ? eventEl.value : '',
                    rating,
                    comments: commentsEl ? commentsEl.value : ''
                };
                const fd = new FormData();
                fd.append('action','event_feedback_submit');
                fd.append('name', record.name);
                fd.append('contact', record.contact);
                fd.append('email', record.email);
                fd.append('event', record.event);
                fd.append('rating', record.rating);
                fd.append('comments', record.comments);
                fetch('user_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' })
                    .then(r=>r.json())
                    .then(d=>{
                        // proceed regardless; local render keeps user view responsive
                    }).catch(()=>{});
                let items = [];
                try { items = JSON.parse(localStorage.getItem('event_feedbacks') || '[]'); } catch(e){ items = []; }
                items.push(record);
                localStorage.setItem('event_feedbacks', JSON.stringify(items));
                closeEventFeedbackModal();
                renderEventFeedbacks();
            });
        }
        
        // PROFILE & SECURITY EVENT LISTENERS
        if (profileSaveBtn) {
            profileSaveBtn.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Profile changes saved successfully!');
                // In a real application, you would submit the form via AJAX
                // const form = document.getElementById('profile-form');
                // const formData = new FormData(form);
                // Submit to server...
            });
        }
        
        if (changeEmailBtn) {
            changeEmailBtn.addEventListener('click', function() {
                alert('Email change functionality would open here.');
                // You could open a modal for email change
            });
        }
        
        if (changeEmailSecurityBtn) {
            changeEmailSecurityBtn.addEventListener('click', function() {
                alert('Email change functionality would open here.');
                // You could open a modal for email change
            });
        }
        
        if (changePasswordBtn) {
            changePasswordBtn.addEventListener('click', function() {
                alert('Password change functionality would open here.');
                // You could open a modal for password change
            });
        }
        
        if (generateApiKeyBtn) {
            generateApiKeyBtn.addEventListener('click', function() {
                const statusEl = this.closest('.security-item').querySelector('.security-status');
                statusEl.innerHTML = '<span class="badge badge-success">API Key Generated</span>';
                alert('API Key generated successfully!');
            });
        }
        
        if (enableApiBtn) {
            enableApiBtn.addEventListener('click', function() {
                const statusEl = this.closest('.security-item').querySelector('.security-status');
                statusEl.innerHTML = '<span class="badge badge-success">API Enabled</span>';
                alert('API Access enabled!');
            });
        }
        
        if (enable2faBtn) {
            enable2faBtn.addEventListener('click', function() {
                const statusEl = this.closest('.security-item').querySelector('.security-status');
                statusEl.innerHTML = '<span class="badge badge-success">Enabled</span>';
                alert('Two-Factor Authentication enabled!');
            });
        }
        
        if (deleteAccountBtn) {
            deleteAccountBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                    alert('Account deletion would be processed here.');
                }
            });
        }
        
        if (exportDataBtn) {
            exportDataBtn.addEventListener('click', function() {
                alert('Your data export has been initiated. You will receive an email when it\'s ready.');
            });
        }
        
        if (deactivateAccountBtn) {
            deactivateAccountBtn.addEventListener('click', function() {
                if (confirm('Are you sure you want to deactivate your account? You can reactivate it later by logging in.')) {
                    alert('Account deactivation would be processed here.');
                }
            });
        }
        
        if (sessionEndBtn) {
            sessionEndBtn.addEventListener('click', function() {
                if (confirm('End this session? You will need to log in again on this device.')) {
                    this.closest('.session-item').style.display = 'none';
                    alert('Session ended successfully.');
                }
            });
        }
        
        function computeAgeFromDateString(s){
            if (!s) return '';
            const d = new Date(s);
            if (isNaN(d.getTime())) return '';
            const now = new Date();
            let age = now.getFullYear() - d.getFullYear();
            const m = now.getMonth() - d.getMonth();
            if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age--;
            return age;
        }
        function setupExclusive(ids){
            const els = ids.map(id=>document.getElementById(id)).filter(Boolean);
            els.forEach(el=>{
                el.addEventListener('change', ()=>{
                    if (el.checked){
                        els.forEach(other=>{ if (other !== el) other.checked = false; });
                    }
                });
            });
        }
        function uncheckChildren(container){
            if (!container) return;
            const inputs = container.querySelectorAll('input[type="checkbox"]');
            inputs.forEach(i=>{ i.checked = false; });
        }
        function updateDaysVisibility(){
            const weekdaysCheckbox = document.getElementById('va_days_weekdays');
            const weekendsCheckbox = document.getElementById('va_days_weekends');
            const weekdayDays = document.getElementById('weekday-days');
            const weekendDays = document.getElementById('weekend-days');
            if (weekdayDays){
                const show = !!(weekdaysCheckbox && weekdaysCheckbox.checked);
                weekdayDays.style.display = show ? 'block' : 'none';
                if (!show) uncheckChildren(weekdayDays);
            }
            if (weekendDays){
                const show = !!(weekendsCheckbox && weekendsCheckbox.checked);
                weekendDays.style.display = show ? 'block' : 'none';
                if (!show) uncheckChildren(weekendDays);
            }
        }
        function initVolunteerForm(){
            const dobInput = document.getElementById('va_dob');
            const ageInput = document.getElementById('va_age');
            if (dobInput && ageInput){
                ageInput.value = computeAgeFromDateString(dobInput.value);
            }
            showFormStep(1);
            setupExclusive(['va_gender_male','va_gender_female']);
            setupExclusive(['va_status_single','va_status_married','va_status_widowed','va_status_separated','va_status_annulled']);
            setupExclusive(['va_night_yes','va_night_no']);
            setupExclusive(['va_fit_yes','va_fit_no']);
            setupExclusive(['va_longperiod_yes','va_longperiod_no']);
            setupExclusive(['va_prev_yes','va_prev_no']);
            const weekdaysCheckbox = document.getElementById('va_days_weekdays');
            const weekendsCheckbox = document.getElementById('va_days_weekends');
            if (weekdaysCheckbox) weekdaysCheckbox.addEventListener('change', updateDaysVisibility);
            if (weekendsCheckbox) weekendsCheckbox.addEventListener('change', updateDaysVisibility);
            updateDaysVisibility();
        }
        const formSteps = [
            document.querySelectorAll('#va-step-1'),
            document.querySelectorAll('#va-step-2'),
            document.querySelectorAll('#va-step-3')
        ];
        let currentFormStep = 1;
        const nextBtn = document.getElementById('volunteer-modal-next');
        const backBtn = document.getElementById('volunteer-modal-back');
        const submitBtn = document.getElementById('volunteer-submit-btn');
        const stepIndicator = document.getElementById('volunteer-modal-step-indicator');
        function showFormStep(n){
            currentFormStep = Math.max(1, Math.min(3, n));
            formSteps.forEach((nodes, idx)=>{
                nodes.forEach(node=>{
                    if (node.classList.contains('modal-step')){
                        node.classList.toggle('active', idx === (currentFormStep-1));
                    }
                });
            });
            if (backBtn) backBtn.style.display = currentFormStep > 1 ? 'inline-block' : 'none';
            if (nextBtn) nextBtn.style.display = currentFormStep < 3 ? 'inline-block' : 'none';
            if (submitBtn) submitBtn.style.display = currentFormStep === 3 ? 'inline-block' : 'none';
            if (stepIndicator) stepIndicator.textContent = 'Page ' + currentFormStep + ' of 3';
            const bodyEl = document.querySelector('#volunteer-modal .modal-body');
            if (bodyEl) bodyEl.style.gridTemplateColumns = currentFormStep === 3 ? '1fr' : '1fr 1fr';
        }
        if (nextBtn){
            nextBtn.addEventListener('click', function(){
                showFormStep(currentFormStep + 1);
            });
        }
        if (backBtn){
            backBtn.addEventListener('click', function(){
                showFormStep(currentFormStep - 1);
            });
        }
        if (confirmDeclineTbody){
            confirmDeclineTbody.addEventListener('click', function(e){
                const target = e.target;
                if (target.classList.contains('assign-confirm-btn') || target.classList.contains('assign-decline-btn')){
                    const id = target.dataset.assignmentId;
                    const status = target.classList.contains('assign-confirm-btn') ? 'Confirmed' : 'Declined';
                    localStorage.setItem('assignmentStatus_'+id, status);
                    const rows = confirmDeclineTbody.querySelectorAll('tr');
                    rows.forEach(row=>{
                        const statusCell = row.querySelector('td[data-status-cell="true"]');
                        if (statusCell && statusCell.dataset.assignmentId === id){
                            statusCell.textContent = status;
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
