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
        header('Content-Type: application/json');
        $uid = $_SESSION['user_id'];
        $type = trim($_POST['type'] ?? '');
        $other = trim($_POST['other'] ?? '');
        $zone = trim($_POST['zone'] ?? '');
        $street = trim($_POST['street'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $days = trim($_POST['days'] ?? '');
        $incident_at = trim($_POST['incident_at'] ?? '');
        $cat = $type === 'other' && $other !== '' ? $other : ($type !== '' ? $type : 'Other');
        $allowedZones = ['Zone 1','Zone 2','Zone 3'];
        $photoUrl = '';
        $videoUrl = '';
        if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $photoUrl = save_uploaded_file($_FILES['photo'], 'reports/photos');
        }
        if (isset($_FILES['video']) && ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $videoUrl = save_uploaded_file($_FILES['video'], 'reports/videos');
        }
        $verified = 0;
        $member = 0;
        try {
            $st = $pdo->prepare("SELECT is_verified, watch_group_member, role FROM users WHERE id = ?");
            $st->execute([$uid]);
            $urow = $st->fetch(PDO::FETCH_ASSOC);
            if ($urow) {
                $verified = (int)($urow['is_verified'] ?? 0);
                $member = (int)($urow['watch_group_member'] ?? 0);
                $r = (string)($urow['role'] ?? '');
                if ($member === 0 && stripos($r, 'WATCH') !== false) $member = 1;
            }
        } catch (Exception $e) {}
        $allowed = ($verified === 1) && ($member === 1);
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS quick_report_logs (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status VARCHAR(30) NOT NULL)"); } catch (Exception $e) {}
        if (!$allowed) {
            try { $insl = $pdo->prepare("INSERT INTO quick_report_logs (user_id, status) VALUES (?, ?)"); $insl->execute([$uid, 'rejected']); } catch (Exception $e) {}
            echo json_encode(['success'=>false,'error'=>'Only verified barangay residents and approved members can submit a Quick Report.']);
            exit();
        }
        if ($cat === '') { echo json_encode(['success'=>false,'error'=>'Invalid type']); exit(); }
        if ($zone === '' || !in_array($zone, $allowedZones, true)) { echo json_encode(['success'=>false,'error'=>'Invalid zone']); exit(); }
        if ($street === '') { echo json_encode(['success'=>false,'error'=>'Invalid street']); exit(); }
        if ($desc === '') { echo json_encode(['success'=>false,'error'=>'Description required']); exit(); }
        if ($photoUrl === '' && $videoUrl === '') { echo json_encode(['success'=>false,'error'=>'Photo or video required']); exit(); }
        if ($incident_at === '') { echo json_encode(['success'=>false,'error'=>'Date & time required']); exit(); }
        $dtOk = true;
        try { $dtOk = (strtotime($incident_at) <= time()); } catch (Exception $e) { $dtOk = false; }
        if (!$dtOk) { echo json_encode(['success'=>false,'error'=>'Date & time cannot be in the future']); exit(); }
        $location = $zone . ' - ' . $street;
        try {
            try { $pdo->exec("CREATE TABLE IF NOT EXISTS watch_observations (id INT AUTO_INCREMENT PRIMARY KEY, observed_at DATETIME NOT NULL, location VARCHAR(255) DEFAULT NULL, category VARCHAR(100) DEFAULT 'Other', description TEXT NOT NULL, status VARCHAR(30) DEFAULT 'pending')"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE watch_observations ADD COLUMN IF NOT EXISTS photo_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE watch_observations ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE watch_observations ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE watch_observations ADD COLUMN IF NOT EXISTS days_of_week VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
            try { $pdo->exec("ALTER TABLE watch_observations ADD COLUMN IF NOT EXISTS incident_at DATETIME DEFAULT NULL"); } catch (Exception $e) {}
            $ins = $pdo->prepare("INSERT INTO watch_observations (observed_at, location, category, description, status, photo_url, video_url, user_id, days_of_week, incident_at) VALUES (NOW(), ?, ?, ?, 'pending', ?, ?, ?, ?, ?)");
            $ins->execute([$location, $cat, $desc, $photoUrl, $videoUrl, $uid, ($days !== '' ? $days : null), $incident_at]);
            try { $insl = $pdo->prepare("INSERT INTO quick_report_logs (user_id, status) VALUES (?, ?)"); $insl->execute([$uid, 'accepted']); } catch (Exception $e) {}
            echo json_encode(['success'=>true]);
            exit();
        } catch (Exception $e) {
            try { $pdo->exec("CREATE TABLE IF NOT EXISTS observations (id INT AUTO_INCREMENT PRIMARY KEY, observed_at DATETIME NOT NULL, location VARCHAR(255) DEFAULT NULL, category VARCHAR(100) DEFAULT 'Other', description TEXT NOT NULL, status VARCHAR(30) DEFAULT 'pending')"); } catch (Exception $e2) {}
            try { $pdo->exec("ALTER TABLE observations ADD COLUMN IF NOT EXISTS photo_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e2) {}
            try { $pdo->exec("ALTER TABLE observations ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e2) {}
            try { $pdo->exec("ALTER TABLE observations ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL"); } catch (Exception $e2) {}
            $ins2 = $pdo->prepare("INSERT INTO observations (observed_at, location, category, description, status, photo_url, video_url, user_id) VALUES (NOW(), ?, ?, ?, 'pending', ?, ?, ?)");
            $ins2->execute([$location, $cat, $desc, $photoUrl, $videoUrl, $uid]);
            try { $insl = $pdo->prepare("INSERT INTO quick_report_logs (user_id, status) VALUES (?, ?)"); $insl->execute([$uid, 'accepted']); } catch (Exception $e) {}
            echo json_encode(['success'=>true]);
            exit();
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
        $incident_date = trim($_POST['incident_date'] ?? '');
        $incident_time = trim($_POST['incident_time'] ?? '');
        $alias = trim($_POST['alias'] ?? '');
        $sign_name = trim($_POST['sign_name'] ?? '');
        $reported_before = isset($_POST['reported_before']) && $_POST['reported_before'] === '1' ? 1 : 0;
        $receive_updates = isset($_POST['receive_updates']) && $_POST['receive_updates'] === '1' ? 1 : 0;
        $contact_pref = trim($_POST['contact_pref'] ?? '');
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
        if ($location === '' || stripos($location, 'commonwealth') === false) {
            echo json_encode(['success'=>false,'error'=>'jurisdiction']);
            exit();
        }
        $photoUrl = '';
        $videoUrl = '';
        $signatureUrl = '';
        if (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $photoUrl = save_uploaded_file($_FILES['photo'], 'complaints/photos');
        }
        if (isset($_FILES['video']) && ($_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $videoUrl = save_uploaded_file($_FILES['video'], 'complaints/videos');
        }
        if (!$anonymous && isset($_FILES['signature']) && ($_FILES['signature']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $signatureUrl = save_uploaded_file($_FILES['signature'], 'complaints/signatures');
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
                urgency VARCHAR(20) DEFAULT NULL,
                incident_date DATE DEFAULT NULL,
                incident_time TIME DEFAULT NULL,
                alias VARCHAR(255) DEFAULT NULL,
                signature_url VARCHAR(255) DEFAULT NULL,
                reported_before TINYINT(1) DEFAULT 0,
                receive_updates TINYINT(1) DEFAULT 0,
                contact_pref VARCHAR(20) DEFAULT NULL
            )");
        } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS photo_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
        try { $pdo->exec("ALTER TABLE complaints ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
        try {
            $ins = $pdo->prepare("INSERT INTO complaints (submitted_at, user_id, resident, issue, category, location, status, anonymous, urgency, photo_url, video_url, incident_date, incident_time, alias, signature_url, reported_before, receive_updates, contact_pref) VALUES (NOW(), ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([
                $uid,
                $resident,
                $desc,
                ($category !== '' ? $category : 'General'),
                $location,
                $anonymous,
                ($urgency !== '' ? $urgency : null),
                $photoUrl,
                $videoUrl,
                ($incident_date !== '' ? $incident_date : null),
                ($incident_time !== '' ? $incident_time : null),
                ($alias !== '' ? $alias : null),
                ($signatureUrl !== '' ? $signatureUrl : null),
                $reported_before,
                $receive_updates,
                ($contact_pref !== '' ? $contact_pref : null)
            ]);
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
        $marital = isset($_POST['cw_marital_status']) ? trim($_POST['cw_marital_status']) : '';
        $signature_rel = null;
        $emg_name = isset($_POST['cw_emergency_name']) ? trim($_POST['cw_emergency_name']) : '';
        $emg_relationship = isset($_POST['cw_emergency_relationship']) ? trim($_POST['cw_emergency_relationship']) : '';
        $emg_address = isset($_POST['cw_emergency_address']) ? trim($_POST['cw_emergency_address']) : '';
        $emg_contact = isset($_POST['cw_emergency_contact']) ? trim($_POST['cw_emergency_contact']) : '';
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS commonwealth_id_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                marital_status VARCHAR(20) DEFAULT NULL,
                signature VARCHAR(255) DEFAULT NULL,
                emergency_contact_name VARCHAR(255) DEFAULT NULL,
                emergency_contact_relationship VARCHAR(100) DEFAULT NULL,
                emergency_contact_address VARCHAR(255) DEFAULT NULL,
                emergency_contact_contact VARCHAR(50) DEFAULT NULL,
                photo_path VARCHAR(255) DEFAULT NULL,
                status ENUM('pending','approved','rejected') DEFAULT 'pending',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (Exception $e) {}
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
        // Optional signature upload
        if (isset($_FILES['signature']) && ($_FILES['signature']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $sigDir = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'signatures' . DIRECTORY_SEPARATOR . $safe;
            if (!is_dir($sigDir)) { @mkdir($sigDir, 0775, true); }
            $stype = @mime_content_type($_FILES['signature']['tmp_name']);
            $sext = 'jpg';
            if ($stype === 'image/png') $sext = 'png';
            elseif ($stype === 'image/webp') $sext = 'webp';
            $sfname = 'sig_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $sext;
            $sdest = $sigDir . DIRECTORY_SEPARATOR . $sfname;
            if (@move_uploaded_file($_FILES['signature']['tmp_name'], $sdest)) {
                $signature_rel = 'scripts/signatures/'.$safe.'/'.$sfname;
            }
        }
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
        $request_id = null;
        try {
            $stmt = $pdo->prepare("INSERT INTO commonwealth_id_requests (user_id, marital_status, signature, emergency_contact_name, emergency_contact_relationship, emergency_contact_address, emergency_contact_contact, photo_path, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $uid,
                ($marital !== '' ? $marital : null),
                ($signature_rel ?: null),
                ($emg_name !== '' ? $emg_name : null),
                ($emg_relationship !== '' ? $emg_relationship : null),
                ($emg_address !== '' ? $emg_address : null),
                ($emg_contact !== '' ? $emg_contact : null),
                $rel
            ]);
            $request_id = $pdo->lastInsertId();
        } catch (Exception $e) {}
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
        echo json_encode(['success'=>true,'path'=>$rel,'person'=>$safe,'trained'=>$trained,'request_id'=>$request_id,'signature_path'=>$signature_rel]);
        exit();
    }
}


$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, middle_name, last_name, role, avatar_url, username, contact, address, date_of_birth, email, is_verified, watch_group_member FROM users WHERE id = ?";
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
    $is_verified = (int)($user['is_verified'] ?? 0);
    $watch_group_member = (int)($user['watch_group_member'] ?? 0);
    
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="../css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/qrious/dist/qrious.min.js"></script>

    <style>
        .modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:none;align-items:center;justify-content:center;z-index:1000}
        .modal-card{width:100%;max-width:700px;max-height:94vh;display:flex;flex-direction:column; background:#fff; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
        /* padding removed from here as it is in card-content */
        .modal-card .card-content{overflow-y:auto;max-height:85vh}
        .modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
        .modal-body{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .modal-step{display:none}
        .modal-step.active{display:block}
        .modal-step.two-col.active{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .modal-body .full{grid-column:1/-1}
        .modal-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:16px}
        .modal-input, .modal-select, .modal-textarea{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:8px;font-size:16px;background-color:#ffffff;}
        .modal-card label{font-size:16px}
        .modal-card input[type="checkbox"]{transform:scale(1.4);margin-right:8px}
        .modal-textarea{min-height:100px;resize:vertical}
        body.dark-mode .modal-input, body.dark-mode .modal-select, body.dark-mode .modal-textarea{background:#1f2937;color:#e5e7eb;border-color:#374151}
        #complaint-modal{backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);background:rgba(255,255,255,0.4)}
        #complaint-modal .modal-header{
            position:relative;
            display:flex;
            align-items:center;
            justify-content:center;
            min-height:80px;
            padding:12px 100px;
        }
        #complaint-modal .modal-header .secondary-button{position:absolute;right:16px;top:50%;transform:translateY(-50%)}
        #complaint-modal .card-title{margin:0 auto}
        #complaint-modal .modal-logo{position:absolute;left:-2px;top:50%;transform:translateY(-50%);width:72px;height:auto}
        /* Volunteer modal specific sizing and style */
        #volunteer-modal .modal-card{max-width:1000px;background:#ffffff;border-radius:12px;}
        #volunteer-modal .card-content{padding:24px;overflow-y:auto;max-height:85vh;}
        #volunteer-modal .modal-header{
            margin-bottom:12px;
            display:flex;
            align-items:center;
            justify-content:center;
            position:relative;
            min-height:80px;
            padding:12px 100px;
        }
        #volunteer-modal .modal-header .secondary-button{
            position:absolute;
            right:16px;
            top:50%;
            transform:translateY(-50%);
        }
        #volunteer-modal .modal-logo{
            position:absolute;
            left:0px;
            top:40%;
            transform:translateY(-50%);
            width:72px;
            height:auto;
        }
        /* Event Registration modal specific sizing and style */
        #event-registration-modal .modal-card{
            max-width:1000px;
            background:#ffffff;
            border-radius:12px;
            width:100%;
        }
        #event-registration-modal .card-content{padding:24px;overflow-y:auto;max-height:85vh;}
        #event-registration-modal .modal-header{
            margin-bottom:12px;
            display:flex;
            align-items:center;
            justify-content:center;
            position:relative;
            min-height:80px;
            padding:12px 100px;
        }
        #event-registration-modal .modal-header .secondary-button{
            position:absolute;
            right:16px;
            top:50%;
            transform:translateY(-50%);
        }
        #event-registration-modal .event-modal-logo{
            position:absolute;
            left:16px;
            top:50%;
            transform:translateY(-50%);
            width:72px;
            height:auto;
        }
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
            <a href="#" class="submenu-item" id="vps-apply-link">Participation & Scheduling Application</a>
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
                        <div class="card" style="background:#ffffff;border-radius:12px;">
                            <div style="background:linear-gradient(90deg,#fde7f3,#fbcfe8);padding:16px 20px;border-radius:12px 12px 0 0;">
                                <h2 class="card-title" style="display:flex;align-items:center;justify-content:center;gap:10px;margin:0;">
                                    <i class="fa-solid fa-bullhorn" style="color:#2563eb;"></i>
                                    Barangay Commonwealth Official Announcement
                                </h2>
                            </div>
                            <div class="card-content" style="padding:20px;">
                                <div style="display:grid;grid-template-columns:1fr;gap:12px;">
                                    <div style="display:flex;align-items:center;gap:10px;font-size:15px;color:#1f2937;">
                                        <i class="fa-regular fa-calendar" style="color:#2563eb;"></i>
                                        <div><strong>Date Posted:</strong> March 10, 2026</div>
                                    </div>
                                    <div style="display:flex;align-items:flex-start;gap:10px;font-size:15px;color:#1f2937;">
                                        <i class="fa-regular fa-file-lines" style="color:#2563eb;margin-top:2px;"></i>
                                        <div>
                                            <strong>Scheduled Barangay Assembly</strong>
                                            <div style="margin-top:6px;line-height:1.7;">
                                                Magkakaroon ng Barangay Assembly sa darating na Sabado, ika-10 ng Marso, alas-3 ng hapon sa Barangay Hall. Inaanyayahan ang lahat ng residente ng Barangay Commonwealth na dumalo.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div style="background:linear-gradient(90deg,#fde7f3,#fbcfe8);padding:12px 20px;border-radius:0 0 12px 12px;">
                                <div style="display:flex;align-items:center;gap:10px;font-size:15px;color:#1f2937;">
                                    <i class="fa-solid fa-landmark" style="color:#2563eb;"></i>
                                    <div>Posted by Barangay Commonwealth Administration</div>
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
            <div id="vps-apply-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Volunteer Participation and Scheduling</h1>
                        <p class="dashboard-subtitle">Apply for participation and scheduling using the simplified form.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="vps-apply-back-btn">Back to Dashboard</button>
                        <button class="primary-button" id="vps-apply-now-btn">Apply Now</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Overview</h2>
                            <p style="margin-top:8px;line-height:1.6;">Provide your personal and contact information, confirm residency, and choose your preferred volunteer role and availability.</p>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Notes</h2>
                            <p style="margin-top:8px;line-height:1.6;">Click Apply Now to open the application form. If you are not a resident of the barangay, the application will be cancelled.</p>
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
            <div id="complaint-quick-report-section" style="display:none;">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Quick Report</h1>
                        <p class="dashboard-subtitle">Residency & Membership validation is required.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="secondary-button" id="complaint-quick-report-back-btn">Back to Dashboard</button>
                    </div>
                </div>
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Create Quick Report</h2>
                            <div style="padding:24px;text-align:center;">
                                <div style="max-width:700px;margin:0 auto 10px auto;font-size:16px;line-height:1.5;">
                                    Submit a time-sensitive incident. Only verified residents and approved members can submit.
                                </div>
                                <button class="primary-button" id="complaint-quick-report-btn" style="display:block;margin:10px auto 0 auto;">Quick Report</button>
                            </div>
                        </div>
                    </div>
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Guidelines</h2>
                            <p style="margin-top:8px;line-height:1.6;">Provide clear incident details, correct zone and street, and at least one photo or short video.</p>
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
            <div class="card-content">
                <div class="modal-header">
                    <img src="../img/cpas-logo.png" alt="Logo" class="modal-logo">
                    <h2 class="card-title">Volunteer Application Form</h2>
                    <button class="secondary-button" id="volunteer-modal-close">Close</button>
                </div>
                <form id="volunteer-form">
                    <div id="volunteer-modal-step-indicator" style="margin-bottom:8px;color:#6b7280;font-size:14px;">Page 1 of 3</div>
                    <div class="modal-body">
                        <div class="modal-step full" id="va-step-1" style="margin-bottom:8px;">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">I. Personal Information</label>
                            <div style="color:#6b7280;margin-top:4px;">Automatically filled from resident record</div>
                        </div>
                        <!-- I. Personal Information -->
                        <div class="modal-step full" id="va-step-1">
                            <label for="va_fullname">Full Name</label>
                            <input id="va_fullname" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                        </div>
                        <div class="modal-step full two-col" id="va-step-1">
                            <div>
                                <label for="va_gender">Gender</label>
                                <select id="va_gender" class="modal-select">
                                    <option value="">Select gender</option>
                                    <option value="male" <?php echo (strtolower($user['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (strtolower($user['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div>
                                <label for="va_civil_status">Civil Status</label>
                                <select id="va_civil_status" class="modal-select">
                                    <option value="">Select civil status</option>
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="widowed">Widowed</option>
                                    <option value="separated">Legally Separated</option>
                                    <option value="annulled">Annulled</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-step full two-col" id="va-step-1">
                            <div>
                                <label for="va_dob">Date of Birth</label>
                                <input id="va_dob" class="modal-input" type="date" value="<?php echo $date_of_birth; ?>" disabled>
                            </div>
                            <div>
                                <label for="va_age">Age</label>
                                <input id="va_age" class="modal-input" type="number" disabled>
                            </div>
                        </div>

                        <!-- II. Contact Information -->
                        <div class="modal-step full" id="va-step-1" style="margin-bottom:8px;">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">II. Contact Information</label>
                        </div>
                        <div class="modal-step full" id="va-step-1">
                            <label for="va_address">Complete Address</label>
                            <input id="va_address" class="modal-input" type="text" value="<?php echo $address; ?>" disabled>
                        </div>
                        <div class="modal-step full two-col" id="va-step-1">
                            <div>
                                <label for="va_contact">Contact Number</label>
                                <input id="va_contact" class="modal-input" type="tel" value="<?php echo $contact; ?>" disabled>
                            </div>
                            <div>
                                <label for="va_email">Email Address</label>
                                <input id="va_email" class="modal-input" type="email" value="<?php echo $email; ?>" disabled>
                            </div>
                        </div>

                        <!-- III. Residency Information -->
                        <div class="modal-step full" id="va-step-1" style="margin-bottom:8px;">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">III. Residency Verification</label>
                            <div style="color:#374151;margin-top:4px;">📍 Only residents of Barangay Commonwealth may apply as volunteers.</div>
                        </div>
                        <div class="modal-step full two-col" id="va-step-1">
                            <div>
                                <label>Residency Status</label>
                                <div><input type="checkbox" id="va_resident_yes"><label for="va_resident_yes" style="margin-left:6px;">Verified Resident of Barangay Commonwealth</label></div>
                                <div><input type="checkbox" id="va_resident_no"><label for="va_resident_no" style="margin-left:6px;">Not a Resident</label></div>
                            </div>
                            <div id="va_residency_length_container" style="display:none;">
                                <label>Length of Residency</label>
                                <div><input type="checkbox" id="va_res_len_lt1"><label for="va_res_len_lt1" style="margin-left:6px;">Less than 1 year</label></div>
                                <div><input type="checkbox" id="va_res_len_1to3"><label for="va_res_len_1to3" style="margin-left:6px;">1–3 years</label></div>
                                <div><input type="checkbox" id="va_res_len_gt3"><label for="va_res_len_gt3" style="margin-left:6px;">More than 3 years</label></div>
                                <div><input type="checkbox" id="va_res_len_other"><label for="va_res_len_other" style="margin-left:6px;">Others (specify)</label></div>
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-1">
                            <div id="va_nonresident_msg" style="display:none;padding:12px;border-radius:8px;background:#fef2f2;color:#991b1b;">Only Barangay Commonwealth residents may apply.</div>
                            <div id="va_res_len_other_wrap" style="display:none;">
                                <label for="va_res_len_other_text">Specify Length of Residency</label>
                                <input id="va_res_len_other_text" class="modal-input" type="text" placeholder="Enter length">
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-1">
                            <div id="va_residency_proof_wrap" style="display:none;">
                                <label for="va_residency_proof">Proof of Residency (upload certificate of residency)</label>
                                <input id="va_residency_proof" class="modal-input" type="file" accept="image/*,.pdf">
                            </div>
                        </div>

                        <!-- IV. Volunteer Preferences -->
                        <div class="modal-step full" id="va-step-1" style="margin-bottom:8px;">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">IV. Volunteer Preferences</label>
                        </div>
                        <div class="modal-step full" id="va-step-1">
                            <label for="va_role">Preferred Volunteer Role</label>
                            <select id="va_role" class="modal-select">
                                <option value="">Select role</option>
                                <option value="community_patrol_assistant">Community Patrol Assistant</option>
                                <option value="cctv_monitoring_support">CCTV Monitoring Support</option>
                                <option value="event_awareness_support">Event and Awareness Support</option>
                                <option value="reporting_observation">Reporting and Observation</option>
                            </select>
                        </div>
                        <div class="modal-step full two-col" id="va-step-1">
                            <div>
                                <label for="va_availability_days">Availability Schedule (Days)</label>
                                <select id="va_availability_days" class="modal-select">
                                    <option value="">Select</option>
                                    <option value="weekdays">Week Days</option>
                                    <option value="weekends">Weekends</option>
                                    <option value="any">Any</option>
                                </select>
                            </div>
                            <div>
                                <label for="va_availability_datetime">Preferred Date and Time</label>
                                <input id="va_availability_datetime" class="modal-input" type="datetime-local">
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-1">
                            <div style="color:#374151;">Specific date, day, and time are required.</div>
                            <label>Days of the Week</label>
                            <div id="va_days_list" style="display:flex;flex-wrap:wrap;gap:12px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="va_day_mon">Monday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="va_day_tue">Tuesday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="va_day_wed">Wednesday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="va_day_thu">Thursday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="va_day_fri">Friday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="va_day_sat">Saturday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="va_day_sun">Sunday</label>
                            </div>
                        </div>

                        <!-- V–X (Second Page) -->
                        <!-- V. Skills and Experience -->
                        <div class="modal-step full" id="va-step-2" style="margin-bottom:8px;">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">V. Skills and Experience</label>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label>Do you have previous volunteer experience?</label>
                            <div><input type="checkbox" id="va_prev_yes"><label for="va_prev_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_prev_no"><label for="va_prev_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <div id="va_prev_specify_wrap" style="display:none;">
                                <label for="va_prev_specify">If yes, please specify</label>
                                <input id="va_prev_specify" class="modal-input" type="text" placeholder="Organization / role">
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label>Relevant Skills</label>
                            <div class="option-list">
                                <div><input type="checkbox" id="va_skill_communication"><label for="va_skill_communication" style="margin-left:6px;">Communication</label></div>
                                <div><input type="checkbox" id="va_skill_firstaid"><label for="va_skill_firstaid" style="margin-left:6px;">Basic First Aid</label></div>
                                <div><input type="checkbox" id="va_skill_computer"><label for="va_skill_computer" style="margin-left:6px;">Computer Literacy</label></div>
                                <div><input type="checkbox" id="va_skill_other"><label for="va_skill_other" style="margin-left:6px;">Others</label></div>
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <div id="va_skill_other_text_wrap" style="display:none;">
                                <label for="va_skill_other_text">Please specify other skills</label>
                                <input id="va_skill_other_text" class="modal-input" type="text" placeholder="Enter skill">
                            </div>
                        </div>

                        <!-- VI. Health and Physical Capability -->
                        <div class="modal-step full" id="va-step-2" style="margin-bottom:8px;">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">VI. Health and Physical Capability</label>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label>Are you physically fit to participate in volunteer activities?</label>
                            <div><input type="checkbox" id="va_fit_yes"><label for="va_fit_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_fit_no"><label for="va_fit_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label>Any medical condition we should be aware of?</label>
                            <div><input type="checkbox" id="va_med_none"><label for="va_med_none" style="margin-left:6px;">None</label></div>
                            <div><input type="checkbox" id="va_med_yes"><label for="va_med_yes" style="margin-left:6px;">Yes</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <div id="va_med_specify_wrap" style="display:none;">
                                <label for="va_med_specify">Please specify medical condition</label>
                                <input id="va_med_specify" class="modal-input" type="text" placeholder="Optional">
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label for="va_medical_record">Provide Medical Record (upload)</label>
                            <input id="va_medical_record" class="modal-input" type="file" accept="image/*,.pdf">
                        </div>

                        <!-- VII. Emergency Contact Information -->
                        <div class="modal-step full" id="va-step-2" style="margin-bottom:8px;">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">VII. Emergency Contact Information</label>
                        </div>
                        <div class="modal-step full two-col" id="va-step-2">
                            <div>
                                <label for="va_emg_name">Emergency Contact Name</label>
                                <input id="va_emg_name" class="modal-input" type="text" placeholder="Full name">
                            </div>
                            <div>
                                <label for="va_emg_relationship">Relationship</label>
                                <input id="va_emg_relationship" class="modal-input" type="text" placeholder="Relationship">
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label for="va_emg_number">Emergency Contact Number</label>
                            <input id="va_emg_number" class="modal-input" type="tel" placeholder="Contact number">
                        </div>
                        <!-- IX. Supporting Documents (Required) -->
                        <div class="modal-step full" id="va-step-2" style="margin-bottom:8px;">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">VIII. Supporting Documents</label>
                            <div style="color:#374151;margin-top:4px;">Accepts PDF, JPG, PNG. Size limits enforced.</div>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label for="va_valid_id">Valid Government ID</label>
                            <input id="va_valid_id" class="modal-input" type="file" accept="image/*,.pdf">
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label for="va_residency_proof_doc">Barangay Certificate (Residency)</label>
                            <input id="va_residency_proof_doc" class="modal-input" type="file" accept="image/*,.pdf">
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label for="va_nbi_clearance">NBI Clearance</label>
                            <input id="va_nbi_clearance" class="modal-input" type="file" accept="image/*,.pdf">
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label for="va_id_photo">ID Photo</label>
                            <input id="va_id_photo" class="modal-input" type="file" accept="image/*">
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <label for="va_other_certs">Other Certificates (Training / Seminar – optional)</label>
                            <input id="va_other_certs" class="modal-input" type="file" accept="image/*,.pdf" multiple>
                        </div>

                        <!-- IX. Declaration and Consent -->
                        <div class="modal-step full" id="va-step-2" style="margin-bottom:8px;">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">IX. Declaration and Consent</label>
                        </div>
                        <div class="modal-step full" id="va-step-2">
                            <div style="margin-bottom:8px;">
                                <input type="checkbox" id="va_declare_agree">
                                <label for="va_declare_agree" style="margin-left:6px;">
                                    I hereby declare that the information provided is true and correct, and I agree to comply with the rules and responsibilities of a Barangay Watch Volunteer.
                                </label>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div>
                                    <label for="va_applicant_name">Applicant’s Name</label>
                                    <input id="va_applicant_name" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                                </div>
                                <div>
                                    <label for="va_digital_signature">Digital Signature</label>
                                    <input id="va_digital_signature" class="modal-input" type="text" placeholder="Type your full name">
                                </div>
                            </div>
                            <div style="margin-top:12px;">
                                <label for="va_date_submitted">Date Submitted</label>
                                <input id="va_date_submitted" class="modal-input" type="datetime-local">
                            </div>
                        </div>

                        <!-- Additional Screening Questions (Third Page) -->
                        <!-- A. Motivation and Commitment -->
                        <div class="modal-step full" id="va-step-3" style="margin-bottom:8px;">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">Additional Screening Questions</label>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label for="va_motivation">1. Why do you want to volunteer for the Barangay Watch Program?</label>
                            <textarea id="va_motivation" class="modal-textarea" placeholder="Short answer"></textarea>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>2. How did you learn about this volunteer opportunity?</label>
                            <div><input type="checkbox" id="va_learn_barangay"><label for="va_learn_barangay" style="margin-left:6px;">Barangay Announcement</label></div>
                            <div><input type="checkbox" id="va_learn_social"><label for="va_learn_social" style="margin-left:6px;">Social Media</label></div>
                            <div><input type="checkbox" id="va_learn_recommendation"><label for="va_learn_recommendation" style="margin-left:6px;">Recommendation from a Resident</label></div>
                            <div><input type="checkbox" id="va_learn_other"><label for="va_learn_other" style="margin-left:6px;">Others (please specify)</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <div id="va_learn_other_text_wrap" style="display:none;">
                                <label for="va_learn_other_text">Please specify how you learned</label>
                                <input id="va_learn_other_text" class="modal-input" type="text" placeholder="Enter source">
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>3. How long are you willing to commit as a volunteer?</label>
                            <div><input type="checkbox" id="va_commit_lt3"><label for="va_commit_lt3" style="margin-left:6px;">Less than 3 months</label></div>
                            <div><input type="checkbox" id="va_commit_3to6"><label for="va_commit_3to6" style="margin-left:6px;">3–6 months</label></div>
                            <div><input type="checkbox" id="va_commit_gt6"><label for="va_commit_gt6" style="margin-left:6px;">More than 6 months</label></div>
                            <div><input type="checkbox" id="va_commit_other"><label for="va_commit_other" style="margin-left:6px;">Others (please specify)</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <div id="va_commit_other_wrap" style="display:none;">
                                <label for="va_commit_other_text">Please specify commitment duration</label>
                                <input id="va_commit_other_text" class="modal-input" type="text" placeholder="Enter duration">
                            </div>
                        </div>

                        <!-- B. Availability and Reliability -->
                        <div class="modal-step full" id="va-step-3">
                            <label>4. How many hours per week can you volunteer?</label>
                            <div><input type="checkbox" id="va_hours_1to5"><label for="va_hours_1to5" style="margin-left:6px;">1–5 hours</label></div>
                            <div><input type="checkbox" id="va_hours_6to10"><label for="va_hours_6to10" style="margin-left:6px;">6–10 hours</label></div>
                            <div><input type="checkbox" id="va_hours_gt10"><label for="va_hours_gt10" style="margin-left:6px;">More than 10 hours</label></div>
                            <div><input type="checkbox" id="va_hours_other"><label for="va_hours_other" style="margin-left:6px;">Others (please specify)</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <div id="va_hours_other_wrap" style="display:none;">
                                <label for="va_hours_other_text">Please specify hours per week</label>
                                <input id="va_hours_other_text" class="modal-input" type="text" placeholder="Enter hours">
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>5. Are you willing to volunteer during emergencies or special events if needed?</label>
                            <div><input type="checkbox" id="va_willing_emergencies_yes"><label for="va_willing_emergencies_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_willing_emergencies_no"><label for="va_willing_emergencies_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>6. Can you attend required orientations and training sessions?</label>
                            <div><input type="checkbox" id="va_attend_training_yes"><label for="va_attend_training_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_attend_training_no"><label for="va_attend_training_no" style="margin-left:6px;">No</label></div>
                        </div>

                        <!-- C. Skills and Experience -->
                        <div class="modal-step full" id="va-step-3">
                            <label>7. Have you participated in any community programs or volunteer work before?</label>
                            <div><input type="checkbox" id="va_participated_yes"><label for="va_participated_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_participated_no"><label for="va_participated_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <div id="va_participated_desc_wrap" style="display:none;">
                                <label for="va_participated_desc">If yes, please describe briefly</label>
                                <input id="va_participated_desc" class="modal-input" type="text" placeholder="Enter description">
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>8. Do you have any of the following skills? (Check all that apply)</label>
                            <div class="option-list">
                                <div><input type="checkbox" id="va_skill_firstaid_2"><label for="va_skill_firstaid_2" style="margin-left:6px;">First Aid / Basic Life Support</label></div>
                                <div><input type="checkbox" id="va_skill_communication_2"><label for="va_skill_communication_2" style="margin-left:6px;">Communication and Public Interaction</label></div>
                                <div><input type="checkbox" id="va_skill_computer_2"><label for="va_skill_computer_2" style="margin-left:6px;">Computer or Mobile App Use</label></div>
                                <div><input type="checkbox" id="va_skill_report_2"><label for="va_skill_report_2" style="margin-left:6px;">Report Writing / Documentation</label></div>
                                <div><input type="checkbox" id="va_skill_none"><label for="va_skill_none" style="margin-left:6px;">None of the above</label></div>
                            </div>
                        </div>

                        <!-- D. Safety and Responsibility -->
                        <div class="modal-step full" id="va-step-3">
                            <label>9. Are you comfortable reporting suspicious activities to barangay officials?</label>
                            <div><input type="checkbox" id="va_comfort_report_yes"><label for="va_comfort_report_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_comfort_report_no"><label for="va_comfort_report_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>10. Can you follow instructions and barangay protocols at all times?</label>
                            <div><input type="checkbox" id="va_follow_protocols_yes"><label for="va_follow_protocols_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_follow_protocols_no"><label for="va_follow_protocols_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>11. Are you willing to work as part of a team with barangay officials and other volunteers?</label>
                            <div><input type="checkbox" id="va_teamwork_yes"><label for="va_teamwork_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_teamwork_no"><label for="va_teamwork_no" style="margin-left:6px;">No</label></div>
                        </div>

                        <!-- E. Background and Eligibility -->
                        <div class="modal-step full" id="va-step-3">
                            <label>12. Have you ever been involved in any criminal case or barangay complaint?</label>
                            <div><input type="checkbox" id="va_criminal_no"><label for="va_criminal_no" style="margin-left:6px;">No</label></div>
                            <div><input type="checkbox" id="va_criminal_yes"><label for="va_criminal_yes" style="margin-left:6px;">Yes</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <div id="va_criminal_specify_wrap" style="display:none;">
                                <label for="va_criminal_specify">If yes, please specify</label>
                                <input id="va_criminal_specify" class="modal-input" type="text" placeholder="Enter details">
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>13. Are you willing to undergo background verification if required?</label>
                            <div><input type="checkbox" id="va_background_yes"><label for="va_background_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_background_no"><label for="va_background_no" style="margin-left:6px;">No</label></div>
                        </div>

                        <!-- F. Health and Physical Readiness -->
                        <div class="modal-step full" id="va-step-3">
                            <label>14. Do you have any medical condition that may affect your volunteer duties?</label>
                            <div><input type="checkbox" id="va_med_affect_none"><label for="va_med_affect_none" style="margin-left:6px;">None</label></div>
                            <div><input type="checkbox" id="va_med_affect_yes"><label for="va_med_affect_yes" style="margin-left:6px;">Yes</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <div id="va_med_affect_specify_wrap" style="display:none;">
                                <label for="va_med_affect_specify">Please specify medical condition</label>
                                <input id="va_med_affect_specify" class="modal-input" type="text" placeholder="Optional">
                            </div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>15. Are you physically able to perform tasks such as walking, standing for long periods, or assisting during events?</label>
                            <div><input type="checkbox" id="va_physically_able_yes"><label for="va_physically_able_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_physically_able_no"><label for="va_physically_able_no" style="margin-left:6px;">No</label></div>
                        </div>

                        <!-- G. Final Confirmation -->
                        <div class="modal-step full" id="va-step-3">
                            <label>16. Do you agree to follow the rules, policies, and code of conduct of the Barangay Watch Volunteer Program?</label>
                            <div><input type="checkbox" id="va_agree_rules_yes"><label for="va_agree_rules_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_agree_rules_no"><label for="va_agree_rules_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full" id="va-step-3">
                            <label>17. Are all the information you provided in this application true and correct?</label>
                            <div><input type="checkbox" id="va_info_true_yes"><label for="va_info_true_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="va_info_true_no"><label for="va_info_true_no" style="margin-left:6px;">No</label></div>
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
                    <h2 class="card-title">Quick Report Form – Residency & Membership Validation</h2>
                    <button class="secondary-button" id="suspicious-close">Close</button>
                </div>
                <form id="suspicious-form">
                    <div class="modal-body">
                        <div class="modal-step full">
                            <label for="incident_type">Type of Incident</label>
                            <select id="incident_type" class="modal-select">
                                <option value="">Select type</option>
                                <option value="suspicious_person">Suspicious Person</option>
                                <option value="theft">Theft</option>
                                <option value="disturbance">Disturbance</option>
                                <option value="vandalism">Vandalism</option>
                                <option value="violence">Violence</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="modal-step full" style="display:none;" data-other-field="true">
                            <label for="incident_other">Specify Other</label>
                            <input id="incident_other" class="modal-input" type="text" placeholder="Enter incident type">
                        </div>
                        <div class="modal-step full two-col active">
                            <div>
                                <label for="incident_zone">Barangay Zone</label>
                                <select id="incident_zone" class="modal-select">
                                    <option value="">Select zone</option>
                                    <option value="Zone 1">Zone 1</option>
                                    <option value="Zone 2">Zone 2</option>
                                    <option value="Zone 3">Zone 3</option>
                                </select>
                            </div>
                            <div>
                                <label for="incident_street">Street</label>
                                <input id="incident_street" class="modal-input" type="text" placeholder="Street name">
                            </div>
                        </div>
                        <div class="modal-step full">
                            <label for="incident_desc">Description</label>
                            <textarea id="incident_desc" class="modal-textarea" placeholder="Short description"></textarea>
                        </div>
                        <div class="modal-step full two-col active">
                            <div>
                                <label for="incident_photo">Upload Photo</label>
                                <input id="incident_photo" class="modal-input" type="file" accept="image/*">
                            </div>
                            <div>
                                <label for="incident_video">Upload Short Video</label>
                                <input id="incident_video" class="modal-input" type="file" accept="video/*">
                            </div>
                        </div>
                        <div class="modal-step full">
                            <label>Days of the Week</label>
                            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qr_day_sun">Sunday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qr_day_mon">Monday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qr_day_tue">Tuesday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qr_day_wed">Wednesday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qr_day_thu">Thursday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qr_day_fri">Friday</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="qr_day_sat">Saturday</label>
                            </div>
                        </div>
                        <div class="modal-step full">
                            <label for="incident_dt">Date & Time of Incident</label>
                            <input id="incident_dt" class="modal-input" type="datetime-local">
                        </div>
                        <div class="modal-step full">
                            <div id="quick-report-access-msg" style="display:none;padding:12px;border-radius:8px;background:#fef2f2;color:#991b1b;">
                                Only verified barangay residents and approved members can submit a Quick Report.
                            </div>
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
    <div id="complaint-modal" class="modal-overlay" style="backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);background:rgba(255,255,255,0.4);">
        <div class="card modal-card" style="max-width:1000px;">
            <div class="card-content" style="padding:24px;position:relative;">
                <div class="modal-header" style="margin-bottom:12px;display:flex;align-items:center;justify-content:center;position:relative;">
                    <img src="../img/cpas-logo.png" alt="Logo" class="modal-logo">
                    <h2 class="card-title">Complaint Form</h2>
                    <button class="secondary-button" id="complaint-close">Close</button>
                </div>
                <form id="complaint-form">
                    <div class="modal-body">
                        <div class="modal-step full" style="margin-bottom:8px;">
                            <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="complaint_anonymous">Submit Anonymously</label>
                        </div>
                        <div class="modal-step full" id="cf-step-1">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">I. Complainant Information</label>
                            <div style="color:#6b7280;margin-bottom:8px;">Automatically filled from resident record</div>
                            <div class="modal-step full cf-personal" style="margin-bottom:8px;">
                                <label for="cf_fullname" style="display:block;margin-bottom:6px;">Full Name</label>
                                <input id="cf_fullname" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                            </div>
                            <div class="modal-step full cf-personal" style="margin-bottom:8px;">
                                <label for="cf_gender" style="display:block;margin-bottom:6px;">Gender</label>
                                <select id="cf_gender" class="modal-select">
                                    <option value="">Select gender</option>
                                    <option value="Male" <?php echo (strtolower($user['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (strtolower($user['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="modal-step full cf-personal" style="margin-bottom:8px;">
                                <label for="cf_contact" style="display:block;margin-bottom:6px;">Contact Number</label>
                                <input id="cf_contact" class="modal-input" type="tel" value="<?php echo $contact; ?>" disabled>
                            </div>
                            <div class="modal-step full cf-personal" style="margin-bottom:8px;">
                                <label for="cf_email" style="display:block;margin-bottom:6px;">Email Address</label>
                                <input id="cf_email" class="modal-input" type="email" value="<?php echo $email; ?>" disabled>
                            </div>
                            <div class="modal-step full" id="cf_alias_wrap" style="display:none;margin-bottom:8px;">
                                <label for="cf_alias" style="display:block;margin-bottom:6px;">Alias</label>
                                <input id="cf_alias" class="modal-input" type="text" placeholder="Optional alias when submitting anonymously">
                            </div>
                        </div>
                        <div class="modal-step full" id="cf-address-step">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">II. Address Information</label>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="cf_address_detail" style="display:block;margin-bottom:6px;">Complete Address (House No., Street, Barangay Commonwealth)</label>
                                <input id="cf_address_detail" class="modal-input" type="text" value="<?php echo $address; ?>" disabled>
                            </div>
                            <div class="modal-step full" style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
                                <span>Residency Verification</span>
                                <span class="badge <?php echo $is_verified ? 'badge-success' : 'badge-warning'; ?>"><?php echo $is_verified ? 'Verified Resident of Barangay Commonwealth' : 'Non-Resident (complaint still allowed)'; ?></span>
                            </div>
                        </div>
                        <div class="modal-step full" id="cf-step-2">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">III. Complaint Details</label>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="cf_type" style="display:block;margin-bottom:6px;">Type of Complaint</label>
                                <select id="cf_type" class="modal-select">
                                    <option value="">Select type</option>
                                    <option value="Theft / Robbery">Theft / Robbery</option>
                                    <option value="Noise Disturbance">Noise Disturbance</option>
                                    <option value="Suspicious Person / Activity">Suspicious Person / Activity</option>
                                    <option value="Vandalism">Vandalism</option>
                                    <option value="Domestic Disturbance">Domestic Disturbance</option>
                                    <option value="Illegal Parking">Illegal Parking</option>
                                    <option value="Public Safety Concern">Public Safety Concern</option>
                                    <option value="Others">Others (enable text input)</option>
                                </select>
                                <div id="cf_type_other_wrap" style="display:none;margin-top:8px;">
                                    <input id="cf_type_other" class="modal-input" type="text" placeholder="Specify other complaint type">
                                </div>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                                <div>
                                    <label for="cf_incident_date" style="display:block;margin-bottom:6px;">Date of Incident</label>
                                    <input id="cf_incident_date" class="modal-input" type="date">
                                </div>
                                <div>
                                    <label for="cf_incident_time" style="display:block;margin-bottom:6px;">Time of Incident</label>
                                    <input id="cf_incident_time" class="modal-input" type="time">
                                </div>
                                <div>
                                    <label for="cf_incident_dow" style="display:block;margin-bottom:6px;">Day of the Week</label>
                                    <input id="cf_incident_dow" class="modal-input" type="text" disabled>
                                </div>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="complaint_location" style="display:block;margin-bottom:6px;">Exact Location of Incident</label>
                                <input id="complaint_location" class="modal-input" type="text" placeholder="e.g., Zone 3, Market Street, Barangay Commonwealth">
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label style="font-weight:600;display:block;margin-bottom:6px;">Urgency Level</label>
                                <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;">
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_urgency_low">Low (non-urgent concern)</label>
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_urgency_medium">Medium (disturbance affecting community)</label>
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cmp_urgency_high">High (immediate danger or emergency)</label>
                                </div>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label style="font-weight:600;display:block;margin-bottom:6px;">IV. Description of Incident</label>
                                <label for="complaint_description" style="display:block;margin-bottom:6px;">Detailed Description</label>
                                <textarea id="complaint_description" class="modal-textarea" placeholder="Please describe what happened, persons involved if known, and other relevant details."></textarea>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label style="font-weight:600;display:block;margin-bottom:6px;">V. Evidence Submission (Optional)</label>
                                <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:8px;">
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ev_photo_chk">Photo</label>
                                    <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ev_video_chk">Video</label>
                                </div>
                                <div class="modal-step full" id="ev_photo_wrap" style="margin-bottom:8px;display:none;">
                                    <label for="complaint_photo" style="display:block;margin-bottom:6px;">Upload Photo</label>
                                    <input id="complaint_photo" class="modal-input" type="file" accept="image/*">
                                </div>
                                <div class="modal-step full" id="ev_video_wrap" style="margin-bottom:8px;display:none;">
                                    <label for="complaint_video" style="display:block;margin-bottom:6px;">Upload Video</label>
                                    <input id="complaint_video" class="modal-input" type="file" accept="video/*">
                                </div>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label style="font-weight:600;display:block;margin-bottom:6px;">VI. Desired Action</label>
                                <label for="cf_desired_action" style="display:block;margin-bottom:6px;">Requested Action</label>
                                <select id="cf_desired_action" class="modal-select">
                                    <option value="">Select action</option>
                                    <option value="Investigation">Investigation</option>
                                    <option value="Mediation">Mediation</option>
                                    <option value="Patrol Monitoring">Patrol Monitoring</option>
                                    <option value="Immediate Assistance">Immediate Assistance</option>
                                    <option value="Other">Others (enable text input)</option>
                                </select>
                                <div id="cf_desired_other_wrap" style="display:none;margin-top:8px;">
                                    <input id="cf_desired_other" class="modal-input" type="text" placeholder="Specify desired action">
                                </div>
                            </div>
                            <div class="modal-step full" style="margin-top:8px;padding:8px;border:1px dashed #d1d5db;border-radius:8px;color:#374151;">
                                📍 This complaint is intended for incidents within Barangay Commonwealth only.
                            </div>
                        </div>
                        <div class="modal-step full" id="cf-step-7">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">VII. Confidentiality and Consent</label>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="consent_truth">I confirm that the information provided is true and accurate</label>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="consent_confidential">I understand that this complaint will be handled confidentially</label>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="consent_data_use">I agree to the use of my data for complaint processing only</label>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;">
                                <label for="cf_sign_name" style="display:block;margin-bottom:6px;">Complainant’s Name or Alias</label>
                                <input id="cf_sign_name" class="modal-input" type="text" placeholder="">
                            </div>
                            <div class="modal-step full" id="cf_signature_wrap" style="margin-bottom:8px;">
                                <label for="complaint_signature" style="display:block;margin-bottom:6px;">Digital Signature</label>
                                <input id="complaint_signature" class="modal-input" type="file" accept="image/*">
                            </div>
                            <div class="modal-step full">
                                <label for="cf_date_submitted" style="display:block;margin-bottom:6px;">Date Submitted</label>
                                <input id="cf_date_submitted" class="modal-input" type="text" disabled>
                            </div>
                        </div>
                        <div class="modal-step full" id="cf-step-8">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">VIII. Optional Follow-Up</label>
                            <div class="modal-step full" style="margin-bottom:8px;display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
                                <span>Have you reported this incident before?</span>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cf_reported_yes">Yes</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cf_reported_no">No</label>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
                                <span>Would you like to receive updates?</span>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cf_updates_yes">Yes</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cf_updates_no">No</label>
                            </div>
                            <div class="modal-step full" style="margin-bottom:8px;display:flex;gap:16px;flex-wrap:wrap;align-items:center;">
                                <span>Preferred Method of Contact</span>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cf_contact_sms">SMS</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="cf_contact_email">Email</label>
                            </div>
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
        <div class="card modal-card" style="background:#fff;max-width:1000px;width:100%;max-height:85vh;overflow:auto;position:relative;">
            <div class="card-content" style="padding:24px;">
                <div class="modal-header">
                    <img src="../img/cpas-logo.png" alt="Logo" class="event-modal-logo">
                    <h2 class="card-title">Event Registration Form</h2>
                    <button class="secondary-button" id="event-modal-close">Close</button>
                </div>
                <form id="event-registration-form">
                    <div style="margin-bottom:8px;color:#6b7280;">Community Events and Outreach Program</div>
                    <div class="modal-body" style="grid-template-columns:1fr;">
                        <div class="modal-step full">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">I. Participant Information</label>
                            <div style="color:#6b7280;margin-top:4px;">Auto-filled from resident record</div>
                            <label style="display:block;margin-bottom:6px;">Full Name</label>
                            <input id="ev_full_name" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                            <label style="display:block;margin:10px 0 6px;">Gender</label>
                            <select id="ev_gender" class="modal-select">
                                <?php $g = strtolower(trim($user['gender'] ?? '')); ?>
                                <option value="">Select gender</option>
                                <option value="Male" <?php echo ($g === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($g === 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Prefer not to say" <?php echo ($g === 'prefer not to say' || $g === 'n/a' || $g === '') ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                            <label style="display:block;margin:10px 0 6px;">Date of Birth</label>
                            <input id="ev_dob" class="modal-input" type="date" value="<?php echo $date_of_birth; ?>" disabled>
                        </div>
                        <div class="modal-step full">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">II. Contact Information</label>
                            <div style="color:#6b7280;margin-top:4px;">Auto-filled from resident record</div>
                            <label style="display:block;margin-bottom:6px;">Contact Number</label>
                            <input id="ev_contact" class="modal-input" type="tel" value="<?php echo htmlspecialchars($contact ?? '', ENT_QUOTES); ?>" disabled>
                            <label style="display:block;margin:10px 0 6px;">Email Address</label>
                            <input id="ev_email" class="modal-input" type="email" value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES); ?>" disabled>
                        </div>
                        <div class="modal-step full">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">III. Address Information</label>
                            <div style="color:#6b7280;margin-top:4px;">Auto-filled from resident record</div>
                            <label style="display:block;margin-bottom:6px;">Complete Address (House No., Street, Barangay Commonwealth)</label>
                            <input id="ev_address" class="modal-input" type="text" value="<?php echo htmlspecialchars($address ?? '', ENT_QUOTES); ?>" disabled>
                        </div>
                        <div class="modal-step full">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">IV. Event Details</label>
                            <label style="display:block;margin-bottom:6px;">Event Title</label>
                            <select id="ev_event_title" class="modal-select">
                                <option value="">Select Event</option>
                            </select>
                            <label style="display:block;margin:10px 0 6px;">Event Date</label>
                            <input id="ev_event_date" class="modal-input" type="date" disabled>
                            <label style="display:block;margin:10px 0 6px;">Event Time</label>
                            <input id="ev_event_time" class="modal-input" type="time" disabled>
                            <label style="display:block;margin:10px 0 6px;">Event Location</label>
                            <select id="ev_event_location" class="modal-select">
                                <option value="Barangay Covered Court">Barangay Covered Court</option>
                                <option value="Barangay Hall">Barangay Hall</option>
                                <option value="Multi-Purpose Hall">Multi-Purpose Hall</option>
                                <option value="School Gymnasium">School Gymnasium</option>
                                <option value="Others">Others</option>
                            </select>
                            <input id="ev_event_location_other" class="modal-input" type="text" placeholder="Specify location" style="display:none;">
                        </div>
                        <div class="modal-step full">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">V. Registration Details</label>
                            <label style="display:block;margin-bottom:6px;">Participant Type</label>
                            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ev_type_resident">Resident</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ev_type_volunteer">Volunteer</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ev_type_personnel">Barangay Personnel</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ev_type_guest">Guest</label>
                            </div>
                            <label style="display:block;margin:10px 0 6px;">Mode of Attendance</label>
                            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;" id="ev_attendance_wrap">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ev_attendance_onsite">On-site</label>
                                <label style="display:flex;align-items:center;gap:8px;display:none;" id="ev_attendance_online_wrap"><input type="checkbox" id="ev_attendance_online">Online</label>
                            </div>
                        </div>
                        <div class="modal-step full">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">VI. Emergency Contact Information</label>
                            <label style="display:block;margin-bottom:6px;">Emergency Contact Name</label>
                            <input id="ev_emg_name" class="modal-input" type="text" placeholder="Full name">
                            <label style="display:block;margin:10px 0 6px;">Relationship</label>
                            <input id="ev_emg_rel" class="modal-input" type="text" placeholder="Relationship">
                            <label style="display:block;margin:10px 0 6px;">Emergency Contact Number</label>
                            <input id="ev_emg_contact" class="modal-input" type="tel" placeholder="Contact number">
                        </div>
                        <div class="modal-step full">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">VII. Special Requirements</label>
                            <label style="display:block;margin-bottom:6px;">Do you have any special needs or medical conditions the organizers should be aware of?</label>
                            <div style="display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ev_special_none">None</label>
                                <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" id="ev_special_yes">Yes</label>
                            </div>
                            <input id="ev_special_text" class="modal-input" type="text" placeholder="Yes (please specify): __________" style="display:none;margin-top:8px;">
                        </div>
                        <div class="modal-step full">
                            <label style="font-weight:600;display:block;margin-bottom:6px;">VIII. Consent and Agreement</label>
                            <label style="display:flex;align-items:flex-start;gap:8px;margin-bottom:12px;">
                                <input type="checkbox" id="ev_consent">
                                <span>I agree to participate in the event and allow the Barangay to collect and record my registration and attendance information for Community Events and Outreach documentation and reporting purposes.</span>
                            </label>
                            <label style="display:block;margin-bottom:6px;">Participant’s Full Name</label>
                            <input id="ev_consent_name" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                            <label style="display:block;margin:10px 0 6px;">Digital Signature</label>
                            <input id="ev_signature" class="modal-input" type="text" placeholder="Digital Signature">
                            <label style="display:block;margin:10px 0 6px;">Date Registered</label>
                            <input id="ev_date_registered" class="modal-input" type="date" disabled>
                            <div style="margin-top:8px;color:#374151;">📌 Please arrive at least 15 minutes before the scheduled event time.</div>
                            <div id="event-consent-error" style="display:none;color:#dc2626;margin-top:8px;">Consent must be checked before submission.</div>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="secondary-button" id="event-modal-cancel">Cancel</button>
                        <button type="submit" class="primary-button" id="event-submit-btn" disabled>Submit Registration</button>
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
    
    <!-- INSTRUCTION PANEL MODAL -->
    <div id="commonwealth-id-instruction-modal" class="modal-overlay" style="display:none; z-index: 9999; align-items: center; justify-content: center;">
        <div class="card modal-card" style="max-width: 800px; width: 95%; max-height: 90vh; overflow-y: auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; flex-direction: column;">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%); padding: 25px; border-bottom: 1px solid #fbcfe8; display: flex; align-items: center; gap: 15px; border-radius: 12px 12px 0 0;">
                 <!-- Icon -->
                 <div style="background: #db2777; padding: 10px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                 </div>
                 <div style="flex: 1;">
                    <h2 style="margin: 0; color: #831843; font-size: 22px; font-weight: 700;">Barangay Commonwealth ID Application Guidelines</h2>
                    <p style="margin: 4px 0 0; color: #be185d; font-size: 14px;">Please read the following instructions carefully before applying.</p>
                 </div>
                 <button id="commonwealth-instruction-close" style="margin-left: auto; background: transparent; border: none; font-size: 24px; color: #831843; cursor: pointer;">&times;</button>
            </div>
    
            <div class="card-content" style="padding: 30px; color: #334155; font-family: sans-serif; overflow-y: auto;">
                
                <!-- Instructions -->
                <div style="display: flex; flex-direction: column; gap: 24px;">
                    
                    <!-- Step 1 -->
                    <div style="display: flex; gap: 16px;">
                        <div style="flex-shrink: 0; width: 32px; height: 32px; background: #db2777; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">1</div>
                        <div>
                            <h3 style="margin: 0 0 8px; color: #0f172a; font-size: 18px; font-weight: 600;">Application Form (Auto-Filled Information)</h3>
                            <p style="margin: 0 0 10px; line-height: 1.5;">The Barangay Commonwealth ID Application Form is automatically populated by the system based on the resident’s registered information.</p>
                            <ul style="margin: 0 0 12px; padding-left: 20px; color: #475569;">
                                <li>Full Name</li>
                                <li>Address</li>
                                <li>Date of Birth</li>
                                <li>Sex</li>
                                <li>Civil Status</li>
                                <li>Emergency Contact Details</li>
                            </ul>
                            <div style="background: #fdf2f8; border-left: 4px solid #ec4899; padding: 12px; border-radius: 4px; font-size: 14px; color: #be185d;">
                                <strong>Important:</strong> If any information is incorrect, request a correction before submitting the application. Incorrect or unverified information may cause delays in processing.
                            </div>
                        </div>
                    </div>
    
                    <!-- Step 2 -->
                    <div style="display: flex; gap: 16px;">
                        <div style="flex-shrink: 0; width: 32px; height: 32px; background: #db2777; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">2</div>
                        <div>
                            <h3 style="margin: 0 0 8px; color: #0f172a; font-size: 18px; font-weight: 600;">Photo Submission / Capture</h3>
                            <ul style="margin: 0 0 12px; padding-left: 20px; color: #475569;">
                                <li>Face clearly visible and facing forward</li>
                                <li>No face coverings (hat, mask, or sunglasses)</li>
                                <li>Well-lit and high-quality image</li>
                            </ul>
                            <div style="background: #fdf2f8; padding: 10px; border-radius: 4px; font-size: 14px; color: #475569; border: 1px solid #e2e8f0;">
                                <strong>Note:</strong> The image will be used for facial recognition for security purposes.
                            </div>
                        </div>
                    </div>
    
                     <!-- Step 3 -->
                     <div style="display: flex; gap: 16px;">
                        <div style="flex-shrink: 0; width: 32px; height: 32px; background: #db2777; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">3</div>
                        <div>
                            <h3 style="margin: 0 0 8px; color: #0f172a; font-size: 18px; font-weight: 600;">Document Verification</h3>
                            <p style="margin: 0; line-height: 1.5; color: #475569;">Submitted information will be reviewed and verified by the Barangay Administration. Applicants may be contacted if additional verification or corrections are required.</p>
                        </div>
                    </div>
    
                     <!-- Step 4 -->
                     <div style="display: flex; gap: 16px;">
                        <div style="flex-shrink: 0; width: 32px; height: 32px; background: #db2777; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">4</div>
                        <div>
                            <h3 style="margin: 0 0 8px; color: #0f172a; font-size: 18px; font-weight: 600;">Signature and Thumbmark</h3>
                             <ul style="margin: 0; padding-left: 20px; color: #475569;">
                                <li>Signature in the designated signature area</li>
                                <li>Right thumbmark for identification and security verification</li>
                            </ul>
                        </div>
                    </div>
    
                     <!-- Step 5 -->
                     <div style="display: flex; gap: 16px;">
                        <div style="flex-shrink: 0; width: 32px; height: 32px; background: #db2777; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">5</div>
                        <div>
                            <h3 style="margin: 0 0 8px; color: #0f172a; font-size: 18px; font-weight: 600;">ID Approval and Release</h3>
                            <p style="margin: 0; line-height: 1.5; color: #475569;">Once the application is approved, the Barangay Commonwealth ID may be previewed and claimed on the scheduled release date.</p>
                        </div>
                    </div>
    
                </div>
    
                <!-- Important Reminders -->
                <div style="margin-top: 30px; border: 2px solid #ec4899; background: #fff; padding: 20px; border-radius: 8px;">
                    <h3 style="margin: 0 0 15px; color: #be185d; text-transform: uppercase; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        IMPORTANT REMINDERS
                    </h3>
                    <ul style="margin: 0; padding-left: 20px; color: #334155; line-height: 1.6;">
                        <li>The Barangay Commonwealth ID is issued only to legitimate residents of Barangay Commonwealth.</li>
                        <li>Providing false or inaccurate information may result in application delay or disapproval.</li>
                    </ul>
                </div>
    
                <!-- Footer -->
                <div style="margin-top: 30px; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 20px; color: #64748b; font-weight: 600;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4 8 4v14"/><path d="M8 21v-2a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span>Barangay Commonwealth Administration</span>
                    </div>

                    <div style="margin-bottom: 20px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <input type="checkbox" id="commonwealth-instruction-acknowledge" style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="commonwealth-instruction-acknowledge" style="cursor: pointer; color: #334155; font-size: 16px;">I have read and understood the instructions.</label>
                    </div>
                    
                    <button id="commonwealth-instruction-proceed" disabled style="background: #94a3b8; color: white; border: none; padding: 14px 30px; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: not-allowed; box-shadow: none; transition: all 0.2s;">
                        Proceed to Application
                    </button>
                </div>
    
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

                <!-- BODY -->
                <div class="modal-body" style="display:grid;grid-template-columns:1fr;gap:14px;">

                    <!-- NAME -->
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div>
                        <label for="cw_last_name">Last Name</label>
                        <input id="cw_last_name" class="modal-input" type="text">
                    </div>
                    <div>
                        <label for="cw_given_name">Given Name</label>
                        <input id="cw_given_name" class="modal-input" type="text">
                    </div>
                    <div>
                        <label for="cw_middle_name">Middle Name</label>
                        <input id="cw_middle_name" class="modal-input" type="text">
                    </div>
                    </div>

                    <!-- ACCOUNT INFO -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label>Username</label>
                        <input class="modal-input" type="text" value="<?php echo $username; ?>" readonly>
                    </div>
                    <div>
                        <label>Contact</label>
                        <input class="modal-input" type="tel" value="<?php echo $contact; ?>" readonly>
                    </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label>Email</label>
                        <input class="modal-input" type="email" value="<?php echo $email; ?>" readonly>
                    </div>
                    <div>
                        <label>Date of Birth</label>
                        <input id="cw_dob" class="modal-input" type="date" value="<?php echo $date_of_birth; ?>" readonly>
                    </div>
                    </div>

                    <!-- SEX -->
                    <div>
                    <label for="cw_sex">Sex</label>
                    <select id="cw_sex" class="modal-select">
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select>
                    </div>

                    <!-- ADDRESS -->
                    <div>
                    <label>Address</label>
                    <input id="cw_address" class="modal-input" type="text" value="<?php echo $address; ?>" disabled>
                    </div>

                    <!-- MARITAL + SIGNATURE -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label for="cw_marital_status">Marital Status</label>
                        <select id="cw_marital_status" class="modal-select">
                        <option value="">Select</option>
                        <option>Single</option>
                        <option>Married</option>
                        <option>Widowed</option>
                        <option>Separated</option>
                        <option>Annulled</option>
                        </select>
                    </div>
                    <div>
                        <label for="cw_signature">Resident’s Signature</label>
                        <input id="cw_signature" class="modal-input" type="file" accept="image/*">
                    </div>
                    </div>

                    <!-- EMERGENCY -->
                    <div style="margin-top:8px;font-weight:600;">
                    In case of emergency, please notify:
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <input id="cw_emg_name" class="modal-input" type="text" placeholder="Name">
                    <input id="cw_emg_relationship" class="modal-input" type="text" placeholder="Relationship">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <input id="cw_emg_address" class="modal-input" type="text" placeholder="Address">
                    <input id="cw_emg_contact" class="modal-input" type="tel" placeholder="Contact No.">
                    </div>

                    <!-- PHOTO -->
                    <div>
                    <label for="commonwealth-photo">Upload Your Photo</label>
                    <input id="commonwealth-photo" class="modal-input" type="file" accept="image/*">

                    <div style="margin-top:10px;padding:12px;border-radius:10px;
                                background:linear-gradient(180deg,#FFFFC5,#FFFFFF);">
                        Note: The image will be used for facial recognition for security purposes.
                    </div>
                    </div>

                </div>

                <!-- ACTIONS -->
                <div class="modal-actions">
                    <button type="button" class="secondary-button" id="commonwealth-id-cancel">Cancel</button>
                    <button type="button" class="secondary-button" id="commonwealth-id-preview">Preview Commonwealth ID</button>
                    <button type="button" class="secondary-button" id="commonwealth-id-submit">Submit</button>
                </div>

                </form>
            </div>
        </div>
    </div>
    
    <div id="commonwealth-preview-modal" class="modal-overlay" style="display:none;">
        <div class="card modal-card">
            <div class="card-content" style="padding:20px;">
                <div class="modal-header">
                    <h2 class="card-title">Commonwealth Barangay ID Preview</h2>
                    <button class="secondary-button" id="commonwealth-preview-close">Close</button>
                </div>
                <div class="modal-body" style="grid-template-columns:1fr;gap:20px;">
                    <div class="modal-step full active">
                        <canvas id="cw_front_canvas" style="width:100%;height:auto;border-radius:8px;"></canvas>
                    </div>
                    <div class="modal-step full active">
                        <canvas id="cw_back_canvas" style="width:100%;height:auto;border-radius:8px;"></canvas>
                    </div>
                </div>
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
    
    <div id="join-watch-modal" class="modal-overlay">
        <div class="card modal-card">
            <div class="card-content" style="padding:20px;">
                <div class="modal-header">
                    <h2 class="card-title">Volunteer Application Form</h2>
                    <button class="secondary-button" id="join-watch-close">Close</button>
                </div>
                <form id="join-watch-form">
                    <div class="modal-body">
                        <div class="modal-step full">
                            <label for="jw_fullname">Full Name</label>
                            <input id="jw_fullname" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                        </div>
                        
                        <div class="modal-step full" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label for="jw_dob">Date of Birth</label>
                                <input id="jw_dob" class="modal-input" type="date" value="<?php echo $date_of_birth; ?>" disabled>
                            </div>
                            <div>
                                <label for="jw_age">Age</label>
                                <input id="jw_age" class="modal-input" type="number" disabled>
                            </div>
                        </div>
                        <div class="modal-step full" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label for="jw_gender">Gender</label>
                                <select id="jw_gender" class="modal-select">
                                    <option value="">Select gender</option>
                                    <option value="male" <?php echo (strtolower($user['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (strtolower($user['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div>
                                <label for="jw_civil_status">Civil Status</label>
                                <select id="jw_civil_status" class="modal-select">
                                    <option value="">Select civil status</option>
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="widowed">Widowed</option>
                                    <option value="separated">Legally Separated</option>
                                    <option value="annulled">Annulled</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-step full">
                            <label for="jw_address">Complete Address (House No., Street, Barangay)</label>
                            <input id="jw_address" class="modal-input" type="text" value="<?php echo $address; ?>" disabled>
                        </div>
                        <div class="modal-step full" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label for="jw_contact">Contact Number</label>
                                <input id="jw_contact" class="modal-input" type="tel" value="<?php echo $contact; ?>" disabled>
                            </div>
                            <div>
                                <label for="jw_email">Email Address</label>
                                <input id="jw_email" class="modal-input" type="email" value="<?php echo $email; ?>" disabled>
                            </div>
                        </div>
                        
                        <div class="modal-step">
                            <label>Are you a resident of the barangay?</label>
                            <div><input type="checkbox" id="jw_resident_yes"><label for="jw_resident_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="jw_resident_no"><label for="jw_resident_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step">
                            <label>Length of Residency in the Barangay</label>
                            <div><input type="checkbox" id="jw_res_len_lt1"><label for="jw_res_len_lt1" style="margin-left:6px;">Less than 1 year</label></div>
                            <div><input type="checkbox" id="jw_res_len_1to3"><label for="jw_res_len_1to3" style="margin-left:6px;">1–3 years</label></div>
                            <div><input type="checkbox" id="jw_res_len_gt3"><label for="jw_res_len_gt3" style="margin-left:6px;">More than 3 years</label></div>
                            <div><input type="checkbox" id="jw_res_len_other"><label for="jw_res_len_other" style="margin-left:6px;">Others (specify)</label></div>
                        </div>
                        <div class="modal-step full" id="jw_res_len_other_wrap" style="display:none;">
                            <label for="jw_res_len_other_text">Specify the length of Residency</label>
                            <input id="jw_res_len_other_text" class="modal-input" type="text" placeholder="Enter length">
                        </div>
                        <div class="modal-step full">
                            <label for="jw_residency_proof">Proof of Residency (upload certificate of residency)</label>
                            <input id="jw_residency_proof" class="modal-input" type="file" accept="image/*,.pdf">
                        </div>
                        
                        <div class="modal-step full">
                            <label for="jw_role">Preferred Volunteer Role</label>
                            <select id="jw_role" class="modal-select">
                                <option value="">Select role</option>
                                <option value="community_patrol_assistant">Community Patrol Assistant</option>
                                <option value="cctv_monitoring_support">CCTV Monitoring Support</option>
                                <option value="event_awareness_support">Event and Awareness Support</option>
                                <option value="reporting_observation">Reporting and Observation</option>
                            </select>
                        </div>
                        <div class="modal-step full">
                            <label for="jw_availability">Availability Schedule</label>
                            <select id="jw_availability" class="modal-select" multiple>
                                <option value="morning">Morning</option>
                                <option value="afternoon">Afternoon</option>
                                <option value="evening">Evening</option>
                                <option value="weekends">Weekends</option>
                            </select>
                        </div>
                        <div class="modal-step full">
                            <label for="jw_preferred_zone">Preferred Duty Area / Zone</label>
                            <select id="jw_preferred_zone" class="modal-select">
                                <option value="">Select area/zone</option>
                                <option value="barangay_hall">Barangay Hall</option>
                                <option value="zone_1">Zone 1</option>
                                <option value="zone_2">Zone 2</option>
                                <option value="zone_3">Zone 3</option>
                                <option value="market_street">Market Street</option>
                            </select>
                        </div>
                        <div class="modal-step full">
                            <label for="jw_preferred_street">Preferred Street</label>
                            <select id="jw_preferred_street" class="modal-select">
                                <option value="">Select street</option>
                                <option>A. Bonifacio</option>
                                <option>Abelardo</option>
                                <option>Adarna ST</option>
                                <option>Aguinaldo</option>
                                <option>Apple St</option>
                                <option>Bacer St</option>
                                <option>Bach</option>
                                <option>Batasan Rd</option>
                                <option>Bato-Bato St</option>
                                <option>Beethoven</option>
                                <option>Bicoleyte</option>
                                <option>Brahms</option>
                                <option>Caridad</option>
                                <option>Chopin</option>
                                <option>Commonwealth Ave</option>
                                <option>Cuenco St</option>
                                <option>D. Carmencita</option>
                                <option>Dear St</option>
                                <option>Debussy</option>
                                <option>Don Benedicto</option>
                                <option>Don Desiderio Ave</option>
                                <option>Don Espejo Ave</option>
                                <option>Don Fabian</option>
                                <option>Don Jose Ave</option>
                                <option>Don Macario</option>
                                <option>Dona Adaucto</option>
                                <option>Dona Agnes</option>
                                <option>Dona Ana Candelaria</option>
                                <option>Dona Carmen Ave</option>
                                <option>Dona Cynthia</option>
                                <option>Dona Fabian Castillo</option>
                                <option>Dona Juliana</option>
                                <option>Dona Lucia</option>
                                <option>Dona Maria</option>
                                <option>Dona Severino</option>
                                <option>Ecol St</option>
                                <option>Elliptical Rd</option>
                                <option>Elma St</option>
                                <option>Ernestine</option>
                                <option>Ernestito</option>
                                <option>Eulogio St</option>
                                <option>Freedom Park</option>
                                <option>Gen. Evangelista</option>
                                <option>Gen. Ricarte</option>
                                <option>Geraldine St</option>
                                <option>Gold St</option>
                                <option>Grapes St</option>
                                <option>Handel</option>
                                <option>Hon. B. Soliven</option>
                                <option>Jasmin St</option>
                                <option>Johan St</option>
                                <option>John Street</option>
                                <option>Julius</option>
                                <option>June June</option>
                                <option>Kalapati St</option>
                                <option>Kamagong St</option>
                                <option>Kasoy St</option>
                                <option>Kasunduan</option>
                                <option>Katibayan St</option>
                                <option>Katipunan St</option>
                                <option>Katuparan</option>
                                <option>Kaunlaran</option>
                                <option>Kilyawan St</option>
                                <option>La Mesa Drive</option>
                                <option>Laurel St</option>
                                <option>Lawin St</option>
                                <option>Liszt</option>
                                <option>Lunas St</option>
                                <option>Ma Theresa</option>
                                <option>Mango</option>
                                <option>Manila Gravel Pit Rd</option>
                                <option>Mark Street</option>
                                <option>Markos Rd</option>
                                <option>Martan St</option>
                                <option>Martirez St</option>
                                <option>Matthew St</option>
                                <option>Melon</option>
                                <option>Mozart</option>
                                <option>Obanc St</option>
                                <option>Ocampo Ave</option>
                                <option>Odigal</option>
                                <option>Pacamara St</option>
                                <option>Pantaleona</option>
                                <option>Paul St</option>
                                <option>Payatas Rd</option>
                                <option>Perez St</option>
                                <option>Pilot Drive</option>
                                <option>Pineapple St</option>
                                <option>Pres. Osmena</option>
                                <option>Pres. Quezon</option>
                                <option>Pres. Roxas</option>
                                <option>Pugo St</option>
                                <option>Republic Ave</option>
                                <option>Riverside Ext</option>
                                <option>Riverside St</option>
                                <option>Rose St</option>
                                <option>Rossini</option>
                                <option>Saint Anthony Street</option>
                                <option>Saint Paul Street</option>
                                <option>San Andres St</option>
                                <option>San Diego St</option>
                                <option>San Miguel St</option>
                                <option>San Pascual</option>
                                <option>San Pedro</option>
                                <option>Sanchez St</option>
                                <option>Santo Nino Street</option>
                                <option>Santo Rosario Street</option>
                                <option>Schubert</option>
                                <option>Simon St</option>
                                <option>Skinita Shortcut</option>
                                <option>Steve St</option>
                                <option>Sto. Nino</option>
                                <option>Strauss</option>
                                <option>Sumapi Drive</option>
                                <option>Tabigo St</option>
                                <option>Thomas St</option>
                                <option>Verdi</option>
                                <option>Villonco</option>
                                <option>Wagner</option>
                            </select>
                        </div>
                        
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="secondary-button" id="join-watch-cancel">Cancel</button>
                        <button type="submit" class="primary-button" id="join-watch-submit">Submit Application</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="join-watch-volunteer-modal" class="modal-overlay" style="display:none;">
        <div class="card modal-card" style="background:#fff;max-width:1000px;width:100%;max-height:85vh;overflow:auto;position:relative;">
            <div class="card-content" style="padding:6px 12px 12px 12px;">
                <div style="position: relative; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; min-height:70px;">
                    <img src="../img/cpas-logo.png" alt="Logo" style="height: 60px; width: auto; position: absolute; left: 0; top: 50%; transform: translateY(-50%);">
                    <h2 style="font-weight: 700; font-size: 24px; margin: 0; text-align: center;">Barangay Watch Group – Application Form</h2>
                </div>
                <form id="join-watch-vol-form">
                    <div class="modal-body" style="grid-template-columns:1fr;gap:12px;">
                        <div class="modal-step full active" data-jwv-page="1" style="font-weight:600;">I. Personal Information (Auto-filled, Read-only)</div>
                        <div class="modal-step full active" data-jwv-page="1" style="color:#6b7280;">Automatically filled from your resident record</div>
                        <div class="modal-step full active" data-jwv-page="1" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                            <div>
                                <label for="jwv_fullname">Full Name</label>
                                <input id="jwv_fullname" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                            </div>
                            <div>
                                <label for="jwv_nickname">Nickname</label>
                                <input id="jwv_nickname" class="modal-input" type="text" value="<?php echo htmlspecialchars($username ?? '', ENT_QUOTES); ?>" disabled>
                            </div>
                            <div>
                                <label for="jwv_gender">Gender</label>
                                <select id="jwv_gender" class="modal-select">
                                    <option value="">Select gender</option>
                                    <option value="male" <?php echo (strtolower($user['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (strtolower($user['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="1" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                            <div>
                                <label for="jwv_age">Age</label>
                                <input id="jwv_age" class="modal-input" type="number" disabled>
                            </div>
                            <div>
                                <label for="jwv_dob">Date of Birth</label>
                                <input id="jwv_dob" class="modal-input" type="date" value="<?php echo $date_of_birth; ?>" disabled>
                            </div>
                            <div>
                                <label for="jwv_civil_status">Civil Status</label>
                                <select id="jwv_civil_status" class="modal-select">
                                    <option value="">Select civil status</option>
                                    <option value="single" <?php echo (strtolower($user['civil_status'] ?? '') === 'single') ? 'selected' : ''; ?>>Single</option>
                                    <option value="married" <?php echo (strtolower($user['civil_status'] ?? '') === 'married') ? 'selected' : ''; ?>>Married</option>
                                    <option value="widowed" <?php echo (strtolower($user['civil_status'] ?? '') === 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="separated" <?php echo (strtolower($user['civil_status'] ?? '') === 'separated') ? 'selected' : ''; ?>>Legally Separated</option>
                                    <option value="annulled" <?php echo (strtolower($user['civil_status'] ?? '') === 'annulled') ? 'selected' : ''; ?>>Annulled</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="1">
                            <label for="jwv_nationality">Nationality</label>
                            <input id="jwv_nationality" class="modal-input" type="text" placeholder="Enter nationality">
                        </div>
                        <div class="modal-step full active" data-jwv-page="1" style="font-weight:600;">II. Contact Details</div>
                        <div class="modal-step full active" data-jwv-page="1" style="color:#6b7280;">Automatically filled from your resident record</div>
                        <div class="modal-step full active" data-jwv-page="1">
                            <label for="jwv_address">Complete Address</label>
                            <input id="jwv_address" class="modal-input" type="text" value="<?php echo $address; ?>" disabled>
                        </div>
                        <div class="modal-step full active" data-jwv-page="1" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label for="jwv_contact">Contact Number</label>
                                <input id="jwv_contact" class="modal-input" type="tel" value="<?php echo $contact; ?>" disabled>
                            </div>
                            <div>
                                <label for="jwv_email">Email Address</label>
                                <input id="jwv_email" class="modal-input" type="email" value="<?php echo $email; ?>" disabled>
                            </div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="1" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label for="jwv_emg_name">Emergency Contact Name</label>
                                <input id="jwv_emg_name" class="modal-input" type="text" placeholder="Enter name">
                            </div>
                            <div>
                                <label for="jwv_emg_contact">Emergency Contact Number</label>
                                <input id="jwv_emg_contact" class="modal-input" type="tel" placeholder="Enter number">
                            </div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="1" style="font-weight:600;">III. Residency Verification</div>
                        <div class="modal-step full active" data-jwv-page="1">
                            <label>Residency Status</label>
                            <div id="jwv_residency_status" style="padding:8px 12px;background:#f9fafb;border-radius:8px;color:#374151;">Checking status…</div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="1">
                            <label>Are you a resident of the barangay?</label>
                            <div><input type="checkbox" id="jwv_resident_yes"><label for="jwv_resident_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="jwv_resident_no"><label for="jwv_resident_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="1" id="jwv_residency_length_container" style="display:none;">
                            <label>Length of Residency</label>
                            <div><input type="checkbox" id="jwv_res_len_lt1"><label for="jwv_res_len_lt1" style="margin-left:6px;">Less than 1 year</label></div>
                            <div><input type="checkbox" id="jwv_res_len_1to3"><label for="jwv_res_len_1to3" style="margin-left:6px;">1–3 years</label></div>
                            <div><input type="checkbox" id="jwv_res_len_gt3"><label for="jwv_res_len_gt3" style="margin-left:6px;">More than 3 years</label></div>
                            <div><input type="checkbox" id="jwv_res_len_other"><label for="jwv_res_len_other" style="margin-left:6px;">Others (specify)</label></div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="1" id="jwv_res_len_other_wrap" style="display:none;">
                            <label for="jwv_res_len_other_text">Specify Length of Residency</label>
                            <input id="jwv_res_len_other_text" class="modal-input" type="text" placeholder="Enter length">
                        </div>
                        <div class="modal-step full active" data-jwv-page="1" id="jwv_residency_proof_wrap" style="display:none;">
                            <label for="jwv_residency_proof">Proof of Residency upload</label>
                            <input id="jwv_residency_proof" class="modal-input" type="file" accept="image/*,.pdf">
                        </div>
                        <div class="modal-step full active" data-jwv-page="1">
                            <label>Barangay Clearance</label>
                            <div><input type="checkbox" id="jwv_clearance_yes"><label for="jwv_clearance_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="jwv_clearance_no"><label for="jwv_clearance_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="1" id="jwv_clearance_wrap" style="display:none;">
                            <label for="jwv_clearance_upload">Upload Barangay Clearance</label>
                            <input id="jwv_clearance_upload" class="modal-input" type="file" accept="image/*,.pdf">
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="font-weight:600;display:none;">IV. Application & Availability Details</div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <label for="jwv_days_select">Availability Schedule Days</label>
                            <select id="jwv_days_select" class="modal-select">
                                <option value="">Select availability schedule</option>
                                <option value="weekdays">Weekdays</option>
                                <option value="weekends">Weekends</option>
                                <option value="any">Any</option>
                            </select>
                            <div id="jwv_days_detail" style="display:none;margin-top:8px;">
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                    <div><input type="checkbox" id="jwv_day_mon"><label for="jwv_day_mon" style="margin-left:6px;">Monday</label></div>
                                    <div><input type="checkbox" id="jwv_day_tue"><label for="jwv_day_tue" style="margin-left:6px;">Tuesday</label></div>
                                    <div><input type="checkbox" id="jwv_day_wed"><label for="jwv_day_wed" style="margin-left:6px;">Wednesday</label></div>
                                    <div><input type="checkbox" id="jwv_day_thu"><label for="jwv_day_thu" style="margin-left:6px;">Thursday</label></div>
                                    <div><input type="checkbox" id="jwv_day_fri"><label for="jwv_day_fri" style="margin-left:6px;">Friday</label></div>
                                    <div><input type="checkbox" id="jwv_day_sat"><label for="jwv_day_sat" style="margin-left:6px;">Saturday</label></div>
                                    <div><input type="checkbox" id="jwv_day_sun"><label for="jwv_day_sun" style="margin-left:6px;">Sunday</label></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <label>Available Time</label>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                                <div><input type="checkbox" id="jwv_time_morning"><label for="jwv_time_morning" style="margin-left:6px;">Morning</label></div>
                                <div><input type="checkbox" id="jwv_time_afternoon"><label for="jwv_time_afternoon" style="margin-left:6px;">Afternoon</label></div>
                                <div><input type="checkbox" id="jwv_time_evening"><label for="jwv_time_evening" style="margin-left:6px;">Evening</label></div>
                                <div><input type="checkbox" id="jwv_time_night"><label for="jwv_time_night" style="margin-left:6px;">Night</label></div>
                            </div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <label for="jwv_zone">Preferred Duty Area / Zone</label>
                            <select id="jwv_zone" class="modal-select">
                                <option value="">Select area/zone</option>
                                <option value="barangay_hall">Barangay Hall</option>
                                <option value="zone_1">Zone 1</option>
                                <option value="zone_2">Zone 2</option>
                                <option value="zone_3">Zone 3</option>
                                <option value="market_street">Market Street</option>
                            </select>
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="font-weight:600;display:none;">V. Legal, Health, and Background Declaration</div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <label>Do you have any criminal record or pending cases?</label>
                            <div><input type="checkbox" id="jwv_criminal_none"><label for="jwv_criminal_none" style="margin-left:6px;">None</label></div>
                            <div><input type="checkbox" id="jwv_criminal_yes"><label for="jwv_criminal_yes" style="margin-left:6px;">Yes</label></div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" id="jwv_criminal_specify_wrap" style="display:none;">
                            <label for="jwv_criminal_specify">Specify</label>
                            <input id="jwv_criminal_specify" class="modal-input" type="text" placeholder="Describe">
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" id="jwv_nbi_wrap" style="display:none;">
                            <label for="jwv_nbi_upload">NBI Clearance</label>
                            <input id="jwv_nbi_upload" class="modal-input" type="file" accept="image/*,.pdf">
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <label>Physical Fitness Declaration</label>
                            <div><input type="checkbox" id="jwv_fit_yes"><label for="jwv_fit_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="jwv_fit_no"><label for="jwv_fit_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <label>Are you willing to undergo background verification?</label>
                            <div><input type="checkbox" id="jwv_background_yes"><label for="jwv_background_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="jwv_background_no"><label for="jwv_background_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <label>Willingness to attend training and orientations</label>
                            <div><input type="checkbox" id="jwv_training_yes"><label for="jwv_training_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="jwv_training_no"><label for="jwv_training_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="font-weight:600;display:none;">VIII. Reason for Applying</div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <label for="jwv_reason">Why do you want to join the Barangay Watch Group?</label>
                            <textarea id="jwv_reason" class="modal-textarea" rows="3" placeholder="Type your reason"></textarea>
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="font-weight:600;display:none;">IX. Uploaded Requirements</div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <label for="jwv_valid_id">Valid Government ID</label>
                            <input id="jwv_valid_id" class="modal-input" type="file" accept="image/*,.pdf">
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="font-weight:600;display:none;">VIII. Barangay Watch Group Role & Coordination</div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;color:#374151;">
                            As a Barangay Watch Group member, your role is limited to observation, reporting, and coordination with barangay officials. Members are not allowed to confront, apprehend, or use force.
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <div><input type="checkbox" id="jwv_conf_observe"><label for="jwv_conf_observe" style="margin-left:6px;">I understand that my role is limited to observation and reporting only</label></div>
                            <div><input type="checkbox" id="jwv_conf_schedule"><label for="jwv_conf_schedule" style="margin-left:6px;">I agree to follow assigned schedules and duty zones</label></div>
                            <div><input type="checkbox" id="jwv_conf_report"><label for="jwv_conf_report" style="margin-left:6px;">I agree to report suspicious activities through the system</label></div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" style="font-weight:600;display:none;">IX. Agreement and Confirmation</div>
                        <div class="modal-step full active" data-jwv-page="2" style="display:none;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" id="jwv_agree">
                                <label for="jwv_agree">I certify that the information provided is true and correct and agree to comply with Barangay Watch Group rules.</label>
                            </div>
                        </div>
                        <div class="modal-step full active" data-jwv-page="2" id="jwv_agree_wrap" style="display:none;">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div>
                                    <label>Applicant’s Name</label>
                                    <input id="jwv_applicant_name" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                                </div>
                                <div>
                                    <label for="jwv_signature">Upload Signature</label>
                                    <input id="jwv_signature" class="modal-input" type="file" accept="image/*,.pdf">
                                </div>
                            </div>
                            <div style="margin-top:12px;">
                                <label for="jwv_date_submitted">Date Submitted</label>
                                <input id="jwv_date_submitted" class="modal-input" type="datetime-local" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="modal-actions" style="display:flex; justify-content:space-between; align-items:center;">
                        <div id="jwv_step_indicator" style="color:#6b7280;font-size:14px;">Page I of II</div>
                        <div style="display:flex; gap:10px;">
                            <button type="button" class="secondary-button" id="join-watch-vol-cancel">Cancel</button>
                            <button type="button" class="secondary-button" id="jwv_back_btn" style="display:none;">Previous</button>
                            <button type="button" class="secondary-button" id="jwv_next_btn">Next</button>
                            <button type="submit" class="primary-button" id="join-watch-vol-submit" style="display:none;">Submit Application</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div id="vps-application-modal" class="modal-overlay">
        <div class="card modal-card">
            <div class="card-content" style="padding:20px;">
                <div class="modal-header">
                    <h2 class="card-title">Volunteer Application Form</h2>
                    <button class="secondary-button" id="vps-app-close">Close</button>
                </div>
                <form id="vps-application-form">
                    <div id="vps_step_indicator" style="margin-bottom:8px;color:#6b7280;font-size:14px;">Page 1 of 3</div>
                    <div class="modal-body">
                        <div class="modal-step full" id="vps-step-1">
                            <label for="vps_fullname">Full Name</label>
                            <input id="vps_fullname" class="modal-input" type="text" value="<?php echo $full_name; ?>" disabled>
                        </div>
                        <div class="modal-step full" id="vps-step-1" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label for="vps_gender">Gender</label>
                                <select id="vps_gender" class="modal-select">
                                    <option value="">Select gender</option>
                                    <option value="male" <?php echo (strtolower($user['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (strtolower($user['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div>
                                <label for="vps_civil_status">Civil Status</label>
                                <select id="vps_civil_status" class="modal-select">
                                    <option value="">Select civil status</option>
                                    <option value="single">Single</option>
                                    <option value="married">Married</option>
                                    <option value="widowed">Widowed</option>
                                    <option value="separated">Legally Separated</option>
                                    <option value="annulled">Annulled</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-step full" id="vps-step-1" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label for="vps_dob">Date of Birth</label>
                                <input id="vps_dob" class="modal-input" type="date" value="<?php echo $date_of_birth; ?>" disabled>
                            </div>
                            <div>
                                <label for="vps_age">Age</label>
                                <input id="vps_age" class="modal-input" type="number" disabled>
                            </div>
                        </div>
                        <div class="modal-step full" id="vps-step-2">
                            <label for="vps_address">Complete Address</label>
                            <input id="vps_address" class="modal-input" type="text" value="<?php echo $address; ?>" disabled>
                        </div>
                        <div class="modal-step full" id="vps-step-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label for="vps_contact">Contact Number</label>
                                <input id="vps_contact" class="modal-input" type="tel" value="<?php echo $contact; ?>" disabled>
                            </div>
                            <div>
                                <label for="vps_email">Email Address</label>
                                <input id="vps_email" class="modal-input" type="email" value="<?php echo $email; ?>" disabled>
                            </div>
                        </div>
                        <div class="modal-step" id="vps-step-2">
                            <label>Are you a resident of this barangay?</label>
                            <div><input type="checkbox" id="vps_resident_yes"><label for="vps_resident_yes" style="margin-left:6px;">Yes</label></div>
                            <div><input type="checkbox" id="vps_resident_no"><label for="vps_resident_no" style="margin-left:6px;">No</label></div>
                        </div>
                        <div class="modal-step" id="vps-step-2">
                            <label>Length of Residency</label>
                            <div><input type="checkbox" id="vps_res_len_lt1"><label for="vps_res_len_lt1" style="margin-left:6px;">Less than 1 year</label></div>
                            <div><input type="checkbox" id="vps_res_len_1to3"><label for="vps_res_len_1to3" style="margin-left:6px;">1–3 years</label></div>
                            <div><input type="checkbox" id="vps_res_len_gt3"><label for="vps_res_len_gt3" style="margin-left:6px;">More than 3 years</label></div>
                            <div><input type="checkbox" id="vps_res_len_other"><label for="vps_res_len_other" style="margin-left:6px;">Others (specify)</label></div>
                        </div>
                        <div class="modal-step full" id="vps_res_len_other_wrap" style="display:none;">
                            <label for="vps_res_len_other_text">Specify Length of Residency</label>
                            <input id="vps_res_len_other_text" class="modal-input" type="text" placeholder="Enter length">
                        </div>
                        <div class="modal-step full" id="vps-step-2">
                            <label for="vps_residency_proof">Proof of Residency (upload certificate of residency)</label>
                            <input id="vps_residency_proof" class="modal-input" type="file" accept="image/*,.pdf">
                        </div>
                        <div class="modal-step full" id="vps-step-3">
                            <label for="vps_role">Preferred Volunteer Role</label>
                            <select id="vps_role" class="modal-select">
                                <option value="">Select role</option>
                                <option value="community_patrol_assistant">Community Patrol Assistant</option>
                                <option value="cctv_monitoring_support">CCTV Monitoring Support</option>
                                <option value="event_awareness_support">Event and Awareness Support</option>
                                <option value="reporting_observation">Reporting and Observation</option>
                            </select>
                        </div>
                        <div class="modal-step full" id="vps-step-3">
                            <label for="vps_availability">Availability Schedule</label>
                            <select id="vps_availability" class="modal-select">
                                <option value="">Select availability</option>
                                <option value="morning">Morning</option>
                                <option value="afternoon">Afternoon</option>
                                <option value="evening">Evening</option>
                                <option value="weekends">Weekends</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="secondary-button" id="vps-app-cancel">Cancel</button>
                        <button type="button" class="secondary-button" id="vps_back_btn" style="display:none;">Back</button>
                        <button type="button" class="secondary-button" id="vps_next_btn">Next</button>
                        <button type="submit" class="primary-button" id="vps-app-submit" style="display:none;">Submit Application</button>
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
        const joinWatchModal = document.getElementById('join-watch-modal');
        const joinWatchClose = document.getElementById('join-watch-close');
        const joinWatchCancel = document.getElementById('join-watch-cancel');
        const joinWatchForm = document.getElementById('join-watch-form');
        const joinWatchVolunteerModal = document.getElementById('join-watch-volunteer-modal');
        const joinWatchVolClose = document.getElementById('join-watch-vol-close');
        const joinWatchVolCancel = document.getElementById('join-watch-vol-cancel');
        const joinWatchVolForm = document.getElementById('join-watch-vol-form');
        const volunteerSubmitBubble = document.getElementById('volunteer-success-bubble');
        const vpsApplyLink = document.getElementById('vps-apply-link');
        const vpsApplySection = document.getElementById('vps-apply-section');
        const vpsApplyBackBtn = document.getElementById('vps-apply-back-btn');
        const vpsApplyNowBtn = document.getElementById('vps-apply-now-btn');
        const vpsModal = document.getElementById('vps-application-modal');
        const vpsClose = document.getElementById('vps-app-close');
        const vpsCancel = document.getElementById('vps-app-cancel');
        const vpsForm = document.getElementById('vps-application-form');
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
        const complaintQuickReportLink = document.getElementById('complaint-quick-report-link');
        const complaintQuickReportSection = document.getElementById('complaint-quick-report-section');
        const complaintQuickReportBackBtn = document.getElementById('complaint-quick-report-back-btn');
        const complaintQuickReportBtn = document.getElementById('complaint-quick-report-btn');
        const suspiciousReportModal = document.getElementById('suspicious-report-modal');
        const suspiciousClose = document.getElementById('suspicious-close');
        const suspiciousCancel = document.getElementById('suspicious-cancel');
        const suspiciousForm = document.getElementById('suspicious-form');
        const incidentTypeSelect = document.getElementById('incident_type');
        const incidentOtherField = document.querySelector('[data-other-field="true"]');
        const incidentZoneSelect = document.getElementById('incident_zone');
        const incidentStreetInput = document.getElementById('incident_street');
        const incidentDtInput = document.getElementById('incident_dt');
        const quickReportAccessMsg = document.getElementById('quick-report-access-msg');
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
        const currentUserVerified = <?php echo (int)$is_verified; ?>;
        const currentUserWatchMember = <?php echo (int)$watch_group_member; ?>;

        const commonwealthOpenBtn = document.getElementById('commonwealth-id-open-btn');
        const commonwealthModal = document.getElementById('commonwealth-id-modal');
        const commonwealthClose = document.getElementById('commonwealth-id-close');
        const commonwealthCancel = document.getElementById('commonwealth-id-cancel');
        const commonwealthSubmit = document.getElementById('commonwealth-id-submit');


        const cwPreviewModal = document.getElementById('commonwealth-preview-modal');
        const cwPreviewClose = document.getElementById('commonwealth-preview-close');
        const commonwealthPreview = document.getElementById('commonwealth-id-preview');

        const cwFrontCanvas = document.getElementById('cw_front_canvas');
        const cwBackCanvas  = document.getElementById('cw_back_canvas');

        const cwLastName   = document.getElementById('cw_last_name');
        const cwGivenName  = document.getElementById('cw_given_name');
        const cwMiddleName = document.getElementById('cw_middle_name');
        const cwDob        = document.getElementById('cw_dob');
        const cwAddress    = document.getElementById('cw_address');
        const cwMarital    = document.getElementById('cw_marital_status');
        const cwSex        = document.getElementById('cw_sex');

        const commonwealthPhoto = document.getElementById('commonwealth-photo');

        const cwEmgName    = document.getElementById('cw_emg_name');
        const cwEmgRel     = document.getElementById('cw_emg_relationship');
        const cwEmgAddr    = document.getElementById('cw_emg_address');
        const cwEmgContact = document.getElementById('cw_emg_contact');
        const cwSig        = document.getElementById('cw_signature');
                
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
            if (vpsApplySection) vpsApplySection.style.display = 'none';
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
        function showVpsApplySection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (vpsApplySection) vpsApplySection.style.display = 'block';
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
        function showComplaintQuickReportSection(){
            if (dashboardSection) dashboardSection.style.display = 'none';
            hideSubmoduleSections();
            if (complaintQuickReportSection) complaintQuickReportSection.style.display = 'block';
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
            document.querySelectorAll('#event-registration-modal .modal-step').forEach(el=>el.classList.add('active'));
            setupExclusive(['ev_attendance_onsite','ev_attendance_online']);
            setupExclusive(['ev_special_none','ev_special_yes']);
            setupExclusive(['ev_type_resident','ev_type_volunteer','ev_type_personnel','ev_type_guest']);
            const specialYes = document.getElementById('ev_special_yes');
            const specialText = document.getElementById('ev_special_text');
            const specialNone = document.getElementById('ev_special_none');
            function updateSpecial(){
                if (specialText) specialText.style.display = !!(specialYes && specialYes.checked);
                if (specialNone && specialNone.checked && specialText) specialText.style.display = 'none';
            }
            [specialYes, specialNone].forEach(el=>{ if (el) el.addEventListener('change', updateSpecial); });
            updateSpecial();
            const dateReg = document.getElementById('ev_date_registered');
            if (dateReg){
                const now = new Date();
                const pad = n => String(n).padStart(2,'0');
                dateReg.value = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
            }
            const consent = document.getElementById('ev_consent');
            const signature = document.getElementById('ev_signature');
            const submitBtn = document.getElementById('event-submit-btn');
            const consentError = document.getElementById('event-consent-error');
            function updateSubmitState(){
                const ok = !!(consent && consent.checked) && !!(signature && signature.value.trim().length) && !!(dateReg && dateReg.value);
                if (submitBtn) submitBtn.disabled = !ok;
            }
            [consent, signature, dateReg].forEach(el=>{ if (el) el.addEventListener('change', updateSubmitState); if (el) el.addEventListener('input', updateSubmitState); });
            updateSubmitState();
            // Emergency contact fields intentionally not auto-filled per spec
            // Participant type auto-detect
            const typeResident = document.getElementById('ev_type_resident');
            const typeVolunteer = document.getElementById('ev_type_volunteer');
            const typePersonnel = document.getElementById('ev_type_personnel');
            const typeGuest = document.getElementById('ev_type_guest');
            [typeResident,typeVolunteer,typePersonnel,typeGuest].forEach(el=>{ if (el) el.checked = false; });
            if (typeof currentUserWatchMember !== 'undefined' && currentUserWatchMember === 1){
                if (typeVolunteer) typeVolunteer.checked = true;
            } else {
                if (typeResident) typeResident.checked = true;
            }
            // Approved Events configuration
            const approvedEvents = [
                { title:'Community Awareness Seminar', date:'<?php echo date("Y-m-d", strtotime("+3 days")); ?>', time:'14:00', location:'Barangay Covered Court', online:false, allowOther:false },
                { title:'Crime Prevention Orientation', date:'<?php echo date("Y-m-d", strtotime("+10 days")); ?>', time:'09:00', location:'Barangay Hall', online:false, allowOther:false },
                { title:'Disaster Preparedness Training', date:'<?php echo date("Y-m-d", strtotime("+17 days")); ?>', time:'13:00', location:'Multi-Purpose Hall', online:false, allowOther:true },
                { title:'Volunteer Orientation', date:'<?php echo date("Y-m-d", strtotime("+5 days")); ?>', time:'15:00', location:'School Gymnasium', online:true, allowOther:false }
            ];
            const evTitle = document.getElementById('ev_event_title');
            const evDate = document.getElementById('ev_event_date');
            const evTime = document.getElementById('ev_event_time');
            const evLoc = document.getElementById('ev_event_location');
            const evLocOther = document.getElementById('ev_event_location_other');
            const onlineWrap = document.getElementById('ev_attendance_online_wrap');
            if (evTitle && evTitle.options.length <= 1){
                approvedEvents.forEach(ev=>{
                    const opt=document.createElement('option');
                    opt.value=ev.title;
                    opt.textContent=ev.title;
                    evTitle.appendChild(opt);
                });
            }
            function applyEventDetails(){
                const selected = approvedEvents.find(e=>e.title === (evTitle?.value || ''));
                if (!selected) {
                    if (evDate) evDate.value='';
                    if (evTime) evTime.value='';
                    if (evLocOther) evLocOther.style.display='none';
                    if (onlineWrap) onlineWrap.style.display='none';
                    return;
                }
                if (evDate) evDate.value = selected.date;
                if (evTime) evTime.value = selected.time;
                if (evLoc) evLoc.value = selected.location;
                if (evLocOther) {
                    const showOther = selected.allowOther && (evLoc.value === 'Others');
                    evLocOther.style.display = showOther ? 'block' : 'none';
                    evLocOther.disabled = !showOther;
                }
                if (onlineWrap) onlineWrap.style.display = selected.online ? 'inline-flex' : 'none';
                const onlineCb = document.getElementById('ev_attendance_online');
                if (onlineCb) onlineCb.checked = false;
            }
            if (evTitle) evTitle.addEventListener('change', applyEventDetails);
            if (evLoc) evLoc.addEventListener('change', function(){
                const selected = approvedEvents.find(e=>e.title === (evTitle?.value || ''));
                if (!selected) return;
                const showOther = selected.allowOther && (evLoc.value === 'Others');
                if (evLocOther) { evLocOther.style.display = showOther ? 'block' : 'none'; evLocOther.disabled = !showOther; }
            });
            applyEventDetails();
            const form = document.getElementById('event-registration-form');
            if (form){
                form.addEventListener('keydown', function(e){
                    if (e.key === 'Enter'){
                        const blocked = submitBtn && submitBtn.disabled;
                        if (blocked){
                            e.preventDefault();
                            if (consentError) consentError.style.display = !!(consent && !consent.checked) ? 'block' : 'none';
                        }
                    }
                });
            }
            const actions = eventRegistrationModal.querySelector('.modal-actions');
            if (actions){
                actions.addEventListener('click', function(){
                    if (submitBtn && submitBtn.disabled){
                        if (consentError) consentError.style.display = !!(consent && !consent.checked) ? 'block' : 'none';
                    }
                }, true);
            }
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
        function openJoinWatchModal(){
            if (joinWatchModal) joinWatchModal.style.display = 'flex';
            document.querySelectorAll('#join-watch-modal .modal-step').forEach(el=>el.classList.add('active'));
            initJoinWatchForm();
        }
        function closeJoinWatchModal(){
            if (joinWatchModal) joinWatchModal.style.display = 'none';
        }
        function initJoinWatchForm(){
            const dobInput = document.getElementById('jw_dob');
            const ageInput = document.getElementById('jw_age');
            if (dobInput && ageInput){
                ageInput.value = computeAgeFromDateString(dobInput.value);
            }
            setupExclusive(['jw_resident_yes','jw_resident_no']);
            setupExclusive(['jw_res_len_lt1','jw_res_len_1to3','jw_res_len_gt3','jw_res_len_other']);
            setupExclusive(['jw_criminal_none','jw_criminal_yes']);
            setupExclusive(['jw_bg_verify_yes','jw_bg_verify_no']);
            const resOther = document.getElementById('jw_res_len_other');
            const resOtherWrap = document.getElementById('jw_res_len_other_wrap');
            if (resOther && resOtherWrap){
                resOtherWrap.style.display = resOther.checked ? 'block' : 'none';
                resOther.addEventListener('change', function(){ resOtherWrap.style.display = resOther.checked ? 'block' : 'none'; });
            }
            const clearanceYes = document.getElementById('jw_clearance_yes');
            const clearanceNo = document.getElementById('jw_clearance_no');
            const clearanceWrap = document.getElementById('jw_clearance_wrap');
            if (clearanceWrap){
                clearanceWrap.style.display = clearanceYes && clearanceYes.checked ? 'block' : 'none';
                if (clearanceYes){
                    clearanceYes.addEventListener('change', function(){ clearanceWrap.style.display = clearanceYes.checked ? 'block' : 'none'; });
                }
                if (clearanceNo){
                    clearanceNo.addEventListener('change', function(){ clearanceWrap.style.display = 'none'; });
                }
            }
            const crNone = document.getElementById('jw_criminal_none');
            const crYes = document.getElementById('jw_criminal_yes');
            const nbiWrap = document.getElementById('jw_nbi_wrap');
            const crSpecifyWrap = document.getElementById('jw_criminal_specify_wrap');
            if (nbiWrap){
                nbiWrap.style.display = crNone && crNone.checked ? 'block' : 'none';
            }
            if (crSpecifyWrap){
                crSpecifyWrap.style.display = crYes && crYes.checked ? 'block' : 'none';
            }
            if (crNone){
                crNone.addEventListener('change', function(){
                    if (nbiWrap) nbiWrap.style.display = crNone.checked ? 'block' : 'none';
                    if (crSpecifyWrap) crSpecifyWrap.style.display = 'none';
                });
            }
            if (crYes){
                crYes.addEventListener('change', function(){
                    if (crSpecifyWrap) crSpecifyWrap.style.display = crYes.checked ? 'block' : 'none';
                    if (nbiWrap) nbiWrap.style.display = 'none';
                });
            }
            const agree = document.getElementById('jw_agree');
            const agreeWrap = document.getElementById('jw_agree_wrap');
            if (agree && agreeWrap){
                agreeWrap.style.display = agree.checked ? 'block' : 'none';
                agree.addEventListener('change', function(){ agreeWrap.style.display = agree.checked ? 'block' : 'none'; });
            }
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
            const now = new Date();
            const pad = n=>String(n).padStart(2,'0');
            const dateEl = document.getElementById('cf_incident_date');
            const timeEl = document.getElementById('cf_incident_time');
            const dowEl = document.getElementById('cf_incident_dow');
            const submittedEl = document.getElementById('cf_date_submitted');
            if (dateEl) dateEl.value = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}`;
            if (timeEl) timeEl.value = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
            if (submittedEl) submittedEl.value = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
            const computeDow = (y,m,d)=>{
                const dt = new Date(`${y}-${pad(m)}-${pad(d)}T00:00:00`);
                const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                return days[dt.getDay()];
            };
            if (dateEl && dowEl){
                const [y,m,d] = dateEl.value.split('-').map(x=>parseInt(x,10));
                if (!isNaN(y) && !isNaN(m) && !isNaN(d)) dowEl.value = computeDow(y,m,d);
                dateEl.addEventListener('change', ()=>{
                    const [yy,mm,dd] = (dateEl.value || '').split('-').map(x=>parseInt(x,10));
                    if (!isNaN(yy) && !isNaN(mm) && !isNaN(dd)) dowEl.value = computeDow(yy,mm,dd);
                });
            }
            const typeSel = document.getElementById('cf_type');
            const typeOtherWrap = document.getElementById('cf_type_other_wrap');
            if (typeSel && typeOtherWrap){
                typeOtherWrap.style.display = typeSel.value === 'Others' ? 'block' : 'none';
                typeSel.addEventListener('change', function(){ typeOtherWrap.style.display = typeSel.value === 'Others' ? 'block' : 'none'; });
            }
            const desiredSel = document.querySelector('#complaint-modal #cf_desired_action');
            const desiredOtherWrap = document.querySelector('#complaint-modal #cf_desired_other_wrap');
            if (desiredSel && desiredOtherWrap){
                desiredOtherWrap.style.display = desiredSel.value === 'Other' ? 'block' : 'none';
                desiredSel.addEventListener('change', function(){ desiredOtherWrap.style.display = desiredSel.value === 'Other' ? 'block' : 'none'; });
            }
            const evPhotoChk = document.getElementById('ev_photo_chk');
            const evVideoChk = document.getElementById('ev_video_chk');
            const evPhotoWrap = document.getElementById('ev_photo_wrap');
            const evVideoWrap = document.getElementById('ev_video_wrap');
            if (evPhotoChk && evPhotoWrap){
                evPhotoWrap.style.display = evPhotoChk.checked ? 'block' : 'none';
                evPhotoChk.addEventListener('change', ()=>{ evPhotoWrap.style.display = evPhotoChk.checked ? 'block' : 'none'; });
            }
            if (evVideoChk && evVideoWrap){
                evVideoWrap.style.display = evVideoChk.checked ? 'block' : 'none';
                evVideoChk.addEventListener('change', ()=>{ evVideoWrap.style.display = evVideoChk.checked ? 'block' : 'none'; });
            }
            const anonChk = document.getElementById('complaint_anonymous');
            if (anonChk){
                const personalBlocks = document.querySelectorAll('#cf-step-1 .cf-personal');
                const aliasWrap = document.getElementById('cf_alias_wrap');
                const sigWrap = document.getElementById('cf_signature_wrap');
                const togglePersonal = ()=>{
                    personalBlocks.forEach(b=>{ b.style.display = anonChk.checked ? 'none' : ''; });
                    if (aliasWrap) aliasWrap.style.display = anonChk.checked ? '' : 'none';
                    if (sigWrap) sigWrap.style.display = anonChk.checked ? 'none' : '';
                };
                togglePersonal();
                anonChk.addEventListener('change', togglePersonal);
            }
            setupExclusive(['cmp_urgency_low','cmp_urgency_medium','cmp_urgency_high']);
            setupExclusive(['cf_reported_yes','cf_reported_no']);
            setupExclusive(['cf_updates_yes','cf_updates_no']);
            setupExclusive(['cf_contact_sms','cf_contact_email']);
        }
        function openJoinWatchVolunteerModal(){
            if (joinWatchVolunteerModal) joinWatchVolunteerModal.style.display = 'flex';
            document.querySelectorAll('#join-watch-volunteer-modal .modal-step').forEach(el=>el.classList.add('active'));
            initJoinWatchVolunteerForm();
            jwvShowFormStep(1);
        }
        function closeJoinWatchVolunteerModal(){
            if (joinWatchVolunteerModal) joinWatchVolunteerModal.style.display = 'none';
        }
        function initJoinWatchVolunteerForm(){
            const dobInput = document.getElementById('jwv_dob');
            const ageInput = document.getElementById('jwv_age');
            if (dobInput && ageInput){
                ageInput.value = computeAgeFromDateString(dobInput.value);
            }
            setupExclusive(['jwv_resident_yes','jwv_resident_no']);
            setupExclusive(['jwv_res_len_lt1','jwv_res_len_1to3','jwv_res_len_gt3','jwv_res_len_other']);
            const resOther = document.getElementById('jwv_res_len_other');
            const resOtherWrap = document.getElementById('jwv_res_len_other_wrap');
            if (resOther && resOtherWrap){
                resOtherWrap.style.display = resOther.checked ? 'block' : 'none';
                resOther.addEventListener('change', function(){ resOtherWrap.style.display = resOther.checked ? 'block' : 'none'; });
            }
            const resNo = document.getElementById('jwv_resident_no');
            const resYes = document.getElementById('jwv_resident_yes');
            const resLenContainer = document.getElementById('jwv_residency_length_container');
            const proofWrap = document.getElementById('jwv_residency_proof_wrap');
            const residStatus = document.getElementById('jwv_residency_status');
            if (residStatus){
                residStatus.textContent = currentUserVerified ? 'Verified Barangay Resident' : 'Not Yet Verified';
                residStatus.style.color = currentUserVerified ? '#059669' : '#b91c1c';
            }
            function toggleResidency(){
                const isYes = !!(resYes && resYes.checked);
                if (resLenContainer) resLenContainer.style.display = isYes ? 'block' : 'none';
                if (resNo && resNo.checked){
                    if (resLenContainer) resLenContainer.style.display = 'none';
                    if (proofWrap) proofWrap.style.display = 'none';
                    alert('Application cancelled: Non-resident of the barangay.');
                    closeJoinWatchVolunteerModal();
                }
            }
            [resYes,resNo].forEach(el=>{ if (el) el.addEventListener('change', toggleResidency); });
            toggleResidency();
            const crimNone = document.getElementById('jwv_criminal_none');
            const crimYes = document.getElementById('jwv_criminal_yes');
            const crimWrap = document.getElementById('jwv_criminal_specify_wrap');
            const nbiWrap = document.getElementById('jwv_nbi_wrap');
            setupExclusive(['jwv_criminal_none','jwv_criminal_yes']);
            function updateCriminalVisibility(){
                const none = !!(crimNone && crimNone.checked);
                const yes = !!(crimYes && crimYes.checked);
                if (crimWrap) crimWrap.style.display = yes ? 'block' : 'none';
                if (nbiWrap) nbiWrap.style.display = none ? 'block' : 'none';
            }
            [crimNone,crimYes].forEach(el=>{ if (el) el.addEventListener('change', updateCriminalVisibility); });
            updateCriminalVisibility();
            setupExclusive(['jwv_background_yes','jwv_background_no']);
            setupExclusive(['jwv_clearance_yes','jwv_clearance_no']);
            const clearanceYes = document.getElementById('jwv_clearance_yes');
            const clearanceNo = document.getElementById('jwv_clearance_no');
            const clearanceWrap = document.getElementById('jwv_clearance_wrap');
            function updateClearance(){
                if (clearanceWrap) clearanceWrap.style.display = !!(clearanceYes && clearanceYes.checked);
            }
            [clearanceYes,clearanceNo].forEach(el=>{ if (el) el.addEventListener('change', updateClearance); });
            updateClearance();
            setupExclusive(['jwv_fit_yes','jwv_fit_no']);
            setupExclusive(['jwv_training_yes','jwv_training_no']);
            (function(){
                const sel = document.getElementById('jwv_days_select');
                const wrap = document.getElementById('jwv_days_detail');
                const idsWeekdays = ['jwv_day_mon','jwv_day_tue','jwv_day_wed','jwv_day_thu','jwv_day_fri'];
                const idsWeekends = ['jwv_day_sat','jwv_day_sun'];
                const all = idsWeekdays.concat(idsWeekends);
                function showDay(id, show){
                    const el = document.getElementById(id);
                    if (el){
                        const row = el.closest('div');
                        if (row) row.style.display = show ? '' : 'none';
                    }
                }
                function updateDays(){
                    const v = (sel && sel.value) ? sel.value : '';
                    if (wrap) wrap.style.display = v ? 'block' : 'none';
                    all.forEach(id=>showDay(id, false));
                    if (v === 'any'){ all.forEach(id=>showDay(id, true)); }
                    else if (v === 'weekdays'){ idsWeekdays.forEach(id=>showDay(id, true)); }
                    else if (v === 'weekends'){ idsWeekends.forEach(id=>showDay(id, true)); }
                }
                if (sel){
                    sel.addEventListener('change', updateDays);
                    updateDays();
                }
            })();
            const agreeEl = document.getElementById('jwv_agree');
            const agreeWrap = document.getElementById('jwv_agree_wrap');
            const agreeDate = document.getElementById('jwv_date_submitted');
            if (agreeEl && agreeWrap){
                agreeWrap.style.display = agreeEl.checked ? 'block' : 'none';
                agreeEl.addEventListener('change', function(){
                    agreeWrap.style.display = agreeEl.checked ? 'block' : 'none';
                    if (agreeEl.checked && agreeDate){
                        const now = new Date();
                        const pad = n => String(n).padStart(2,'0');
                        agreeDate.value = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
                    }
                });
            }
        }
        function openVpsApplicationModal(){
            if (vpsModal) vpsModal.style.display = 'flex';
            document.querySelectorAll('#vps-application-modal .modal-step').forEach(el=>el.classList.add('active'));
            initVpsApplicationForm();
            vpsShowFormStep(1);
        }
        function closeVpsApplicationModal(){
            if (vpsModal) vpsModal.style.display = 'none';
        }
        function initVpsApplicationForm(){
            const dobInput = document.getElementById('vps_dob');
            const ageInput = document.getElementById('vps_age');
            if (dobInput && ageInput){
                ageInput.value = computeAgeFromDateString(dobInput.value);
            }
            setupExclusive(['vps_resident_yes','vps_resident_no']);
            setupExclusive(['vps_res_len_lt1','vps_res_len_1to3','vps_res_len_gt3','vps_res_len_other']);
            const resOther = document.getElementById('vps_res_len_other');
            const resOtherWrap = document.getElementById('vps_res_len_other_wrap');
            if (resOther && resOtherWrap){
                resOtherWrap.style.display = resOther.checked ? 'block' : 'none';
                resOther.addEventListener('change', function(){ resOtherWrap.style.display = resOther.checked ? 'block' : 'none'; });
            }
            const resNo = document.getElementById('vps_resident_no');
            if (resNo){
                resNo.addEventListener('change', function(){
                    if (resNo.checked){
                        alert('Application cancelled: Non-resident of the barangay.');
                        closeVpsApplicationModal();
                    }
                });
            }
        }
        const vpsFormSteps = [
            document.querySelectorAll('#vps-step-1'),
            document.querySelectorAll('#vps-step-2'),
            document.querySelectorAll('#vps-step-3')
        ];
        let vpsCurrentFormStep = 1;
        function vpsShowFormStep(n){
            vpsCurrentFormStep = Math.max(1, Math.min(3, n));
            vpsFormSteps.forEach((nodes, idx)=>{
                nodes.forEach(node=>{
                    if (node.classList.contains('modal-step')){
                        node.style.display = idx === (vpsCurrentFormStep-1) ? '' : 'none';
                    }
                });
            });
            const backBtn = document.getElementById('vps_back_btn');
            const nextBtn = document.getElementById('vps_next_btn');
            const submitBtn = document.getElementById('vps-app-submit');
            const stepIndicator = document.getElementById('vps_step_indicator');
            if (backBtn) backBtn.style.display = vpsCurrentFormStep > 1 ? 'inline-block' : 'none';
            if (nextBtn) nextBtn.style.display = vpsCurrentFormStep < 3 ? 'inline-block' : 'none';
            if (submitBtn) submitBtn.style.display = vpsCurrentFormStep === 3 ? 'inline-block' : 'none';
            if (stepIndicator) stepIndicator.textContent = 'Page ' + vpsCurrentFormStep + ' of 3';
            const bodyEl = document.querySelector('#vps-application-modal .modal-body');
            if (bodyEl) bodyEl.style.gridTemplateColumns = vpsCurrentFormStep === 3 ? '1fr' : '1fr 1fr';
        }
        (function(){
            const nextBtn = document.getElementById('vps_next_btn');
            const backBtn = document.getElementById('vps_back_btn');
            if (nextBtn){
                nextBtn.addEventListener('click', function(){
                    vpsShowFormStep(vpsCurrentFormStep + 1);
                });
            }
            if (backBtn){
                backBtn.addEventListener('click', function(){
                    vpsShowFormStep(vpsCurrentFormStep - 1);
                });
            }
        })();



        let jwvCurrentFormStep = 1;
        function jwvShowFormStep(n){
            jwvCurrentFormStep = Math.max(1, Math.min(2, n));
            const steps = document.querySelectorAll('#join-watch-volunteer-modal .modal-step');
            steps.forEach(node=>{
                const page = node.getAttribute('data-jwv-page') || '1';
                const show = String(jwvCurrentFormStep) === String(page);
                if (node.getAttribute('data-jwv-page')) node.style.display = show ? '' : 'none';
                node.classList.toggle('active', show);
            });
            const backBtn = document.getElementById('jwv_back_btn');
            const nextBtn = document.getElementById('jwv_next_btn');
            const submitBtn = document.getElementById('join-watch-vol-submit');
            const stepIndicator = document.getElementById('jwv_step_indicator');
            if (backBtn) backBtn.style.display = jwvCurrentFormStep > 1 ? 'inline-block' : 'none';
            if (nextBtn) nextBtn.style.display = jwvCurrentFormStep < 2 ? 'inline-block' : 'none';
            if (submitBtn) submitBtn.style.display = jwvCurrentFormStep === 2 ? 'inline-block' : 'none';
            if (stepIndicator) stepIndicator.textContent = jwvCurrentFormStep === 1 ? 'Page I of II' : 'Page II of II';
            const bodyEl = document.querySelector('#join-watch-volunteer-modal .modal-body');
            if (bodyEl) bodyEl.style.gridTemplateColumns = '1fr';
        }
        (function(){
            const nextBtn = document.getElementById('jwv_next_btn');
            const backBtn = document.getElementById('jwv_back_btn');
            if (nextBtn){
                nextBtn.addEventListener('click', function(){
                    jwvShowFormStep(jwvCurrentFormStep + 1);
                });
            }
            if (backBtn){
                backBtn.addEventListener('click', function(){
                    jwvShowFormStep(jwvCurrentFormStep - 1);
                });
            }
        })();

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



        document.addEventListener('DOMContentLoaded', () => {

        /* =====================================================
        HELPERS
        ===================================================== */
        function drawRoundedBox(ctx, x, y, w, h, r, fill, stroke) {
            ctx.beginPath();
            ctx.moveTo(x+r, y);
            ctx.lineTo(x+w-r, y);
            ctx.quadraticCurveTo(x+w, y, x+w, y+r);
            ctx.lineTo(x+w, y+h-r);
            ctx.quadraticCurveTo(x+w, y+h, x+w-r, y+h);
            ctx.lineTo(x+r, y+h);
            ctx.quadraticCurveTo(x, y+h, x, y+h-r);
            ctx.lineTo(x, y+r);
            ctx.quadraticCurveTo(x, y, x+r, y);
            ctx.closePath();
            ctx.fillStyle = fill;
            ctx.fill();
            ctx.strokeStyle = stroke;
            ctx.lineWidth = 2;
            ctx.stroke();
        }

        function drawFieldWithLabel(ctx, W, H, x, y, w, h, label) {
            // LABEL (LAKIAN)
            ctx.font = '700 16px Arial'; // dati 14px
            ctx.fillStyle = '#0b4ea2';
            ctx.fillText(label.toUpperCase(), W*x + 6, H*y - 8);

            // FIELD
            drawRoundedBox(
                ctx,
                W*x,
                H*y,
                W*w,
                H*h,
                10,
                'rgba(255,255,255,0.96)',
                '#0b4ea2'
            );
        }
        function wrapText(ctx, text, x, y, maxWidth, lineHeight) {
            const words = text.split(' ');
            let line = '';
            let yy = y;

            for (let i = 0; i < words.length; i++) {
                const testLine = line + words[i] + ' ';
                const metrics = ctx.measureText(testLine);
                if (metrics.width > maxWidth && i > 0) {
                    ctx.fillText(line, x, yy);
                    line = words[i] + ' ';
                    yy += lineHeight;
                } else {
                    line = testLine;
                }
            }
            ctx.fillText(line, x, yy);
        }

        function drawWrappedText(ctx, W, H, x, y, w, text, bold=false) {
            if (!text) return;
            ctx.font = `${bold ? '700' : '500'} 20px Arial`;
            ctx.fillStyle = '#000';
            wrapText(
                ctx,
                text.toUpperCase(),
                W*x + 10,
                H*y + 26,
                W*w - 20,
                24
            );
        }

        function formatDOB(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        }
        function drawFieldBox(ctx, W, H, x, y, w, h) {
            ctx.fillStyle = 'rgba(255,255,255,0.95)';
            ctx.fillRect(W * x, H * y, W * w, H * h);
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 1;
            ctx.strokeRect(W * x, H * y, W * w, H * h);
        }

        function drawText(ctx, W, H, x, y, text, bold = false, size = 22) {
            if (!text) return;
            ctx.font = `${bold ? '700' : '500'} ${size}px Arial`;
            ctx.fillStyle = '#000';
            ctx.fillText(text.toUpperCase(), W * x + 8, H * y + size + 4);
        }

        async function drawAutoFitImage(ctx, img, x, y, w, h) {
            const scale = Math.min(w / img.width, h / img.height);
            const nw = img.width * scale;
            const nh = img.height * scale;
            ctx.drawImage(img, x + (w - nw) / 2, y + (h - nh) / 2, nw, nh);
        }

        async function drawCover(ctx, img, x, y, w, h) {
            const scale = Math.max(w / img.width, h / img.height);
            ctx.drawImage(
                img,
                x + (w - img.width * scale) / 2,
                y + (h - img.height * scale) / 2,
                img.width * scale,
                img.height * scale
            );
        }

        /* =====================================================
        MODALS
        ===================================================== */
        const openBtn   = document.getElementById('commonwealth-id-open-btn');
        const modal     = document.getElementById('commonwealth-id-modal');
        const closeBtn  = document.getElementById('commonwealth-id-close');
        const cancelBtn = document.getElementById('commonwealth-id-cancel');

        // INSTRUCTION MODAL ELEMENTS
        const instructionModal = document.getElementById('commonwealth-id-instruction-modal');
        const instructionCloseBtn = document.getElementById('commonwealth-instruction-close');
        const instructionProceedBtn = document.getElementById('commonwealth-instruction-proceed');
        const instructionAckCheckbox = document.getElementById('commonwealth-instruction-acknowledge');

        const previewBtn   = document.getElementById('commonwealth-id-preview');
        const previewModal = document.getElementById('commonwealth-preview-modal');
        const previewClose = document.getElementById('commonwealth-preview-close');

        // OPEN INSTRUCTION MODAL INSTEAD OF FORM
        openBtn?.addEventListener('click', () => {
            if (instructionModal) {
                instructionModal.style.display = 'flex';
                // Reset checkbox and button state when opening
                if (instructionAckCheckbox) instructionAckCheckbox.checked = false;
                if (instructionProceedBtn) {
                    instructionProceedBtn.disabled = true;
                    instructionProceedBtn.style.background = '#94a3b8';
                    instructionProceedBtn.style.cursor = 'not-allowed';
                    instructionProceedBtn.style.boxShadow = 'none';
                }
            }
        });

        // TOGGLE PROCEED BUTTON BASED ON CHECKBOX
        instructionAckCheckbox?.addEventListener('change', (e) => {
            if (instructionProceedBtn) {
                if (e.target.checked) {
                    instructionProceedBtn.disabled = false;
                    instructionProceedBtn.style.background = 'linear-gradient(90deg, #ec4899, #f472b6)';
                    instructionProceedBtn.style.cursor = 'pointer';
                    instructionProceedBtn.style.boxShadow = '0 4px 12px rgba(236, 72, 153, 0.3)';
                } else {
                    instructionProceedBtn.disabled = true;
                    instructionProceedBtn.style.background = '#94a3b8';
                    instructionProceedBtn.style.cursor = 'not-allowed';
                    instructionProceedBtn.style.boxShadow = 'none';
                }
            }
        });

        // CLOSE INSTRUCTION MODAL
        instructionCloseBtn?.addEventListener('click', () => {
            if (instructionModal) instructionModal.style.display = 'none';
        });

        // PROCEED TO FORM
        instructionProceedBtn?.addEventListener('click', () => {
            if (instructionModal) instructionModal.style.display = 'none';
            if (modal) modal.style.display = 'flex';
        });

        closeBtn?.addEventListener('click', () => modal.style.display = 'none');
        cancelBtn?.addEventListener('click', () => modal.style.display = 'none');
        previewClose?.addEventListener('click', () => previewModal.style.display = 'none');

        /* =====================================================
        CANVAS
        ===================================================== */
        const cwFrontCanvas = document.getElementById('cw_front_canvas');
        const cwBackCanvas  = document.getElementById('cw_back_canvas');

        const photoInput = document.getElementById('commonwealth-photo');
        const sigInput   = document.getElementById('cw_signature');

        /* =====================================================
        FIELD POSITIONS (MATCH YOUR IMAGE)
        ===================================================== */
       const FRONT_FIELDS = [
            { id:'cw_dob',            x:0.36, y:0.60, label:'Date of Birth' },
            { id:'cw_address',        x:0.36, y:0.70, label:'Address', wrap:true },
            { id:'cw_marital_status', x:0.36, y:0.82, label:'Marital Status' },
            { id:'cw_sex',            x:0.36, y:0.92, label:'Sex' }
        ];

        const BACK_FIELDS = [
            { id:'cw_emg_name',         x:0.10, y:0.31, label:'Name' },
            { id:'cw_emg_address',      x:0.10, y:0.40, label:'Address', wrap:true },
            { id:'cw_emg_relationship', x:0.10, y:0.52, label:'Relationship' },
            { id:'cw_emg_contact',      x:0.10, y:0.61, label:'Contact No.' }
        ];

        /* =====================================================
        PREVIEW RENDER
        ===================================================== */
        async function renderPreview() {

            const frontImg = new Image();
            const backImg  = new Image();

            frontImg.src = '../img/Front_ID.png';
            backImg.src  = '../img/Back_ID.png';

            await Promise.all([frontImg.decode(), backImg.decode()]);

            const dpr = window.devicePixelRatio || 1;

            cwFrontCanvas.width  = frontImg.width  * dpr;
            cwFrontCanvas.height = frontImg.height * dpr;
            cwBackCanvas.width   = backImg.width   * dpr;
            cwBackCanvas.height  = backImg.height  * dpr;

            const fctx = cwFrontCanvas.getContext('2d');
            const bctx = cwBackCanvas.getContext('2d');

            fctx.setTransform(dpr,0,0,dpr,0,0);
            bctx.setTransform(dpr,0,0,dpr,0,0);

            /* BASE */
            fctx.drawImage(frontImg,0,0);
            bctx.drawImage(backImg,0,0);

            /* ================= FULL NAME ================= */
            const lname = document.getElementById('cw_last_name')?.value || '';
            const fname = document.getElementById('cw_given_name')?.value || '';
            const mname = document.getElementById('cw_middle_name')?.value || '';

            const fullName = `${lname}, ${fname} ${mname}`.trim();
            
            const NAME_FIELDS = [
                { label: 'Last Name',   value: lname, x: 0.36, y: 0.30 },
                { label: 'Surname',     value: fname, x: 0.36, y: 0.40 },
                { label: 'Middle Name', value: mname, x: 0.36, y: 0.50 }
            ];
            NAME_FIELDS.forEach(n=>{
                drawFieldWithLabel(
                    fctx,
                    frontImg.width,
                    frontImg.height,
                    n.x, n.y, 0.60, 0.06,
                    n.label
                );
                drawWrappedText(
                    fctx,
                    frontImg.width,
                    frontImg.height,
                    n.x, n.y, 0.60,
                    n.value || '',
                    false
                );
            });

            /* ================= FRONT FIELDS ================= */
            FRONT_FIELDS.forEach(f => {
                let value = document.getElementById(f.id)?.value || '';
                if (f.id === 'cw_dob') value = formatDOB(value);

                drawFieldWithLabel(
                    fctx,
                    frontImg.width,
                    frontImg.height,
                    f.x, f.y, 0.60, f.wrap ? 0.08 : 0.06,
                    f.label
                );

                drawWrappedText(
                    fctx,
                    frontImg.width,
                    frontImg.height,
                    f.x, f.y, 0.60,
                    value,
                    false
                );
            });

            /* ================= PHOTO ================= */
            if (photoInput?.files?.length) {
                const img = new Image();
                img.src = URL.createObjectURL(photoInput.files[0]);
                await img.decode();
                const photoX = frontImg.width  * 0.075;
                const photoY = frontImg.height * 0.305;
                const photoW = frontImg.width  * 0.23;
                const photoH = frontImg.height * 0.40;
                await drawCover(fctx, img, photoX, photoY, photoW, photoH);
            }

            /* ================= QR CODE ================= */
            const qrCanvas = document.createElement('canvas');
            new QRious({
                element: qrCanvas,
                size: 180,
                value: JSON.stringify({
                    name: fullName,
                    dob: document.getElementById('cw_dob')?.value,
                    address: document.getElementById('cw_address')?.value
                })
            });

            {
                const photoX = frontImg.width  * 0.075;
                const photoY = frontImg.height * 0.305;
                const photoW = frontImg.width  * 0.23;
                const photoH = frontImg.height * 0.40;
                const qrSize = photoW * 0.60;
                const qrX = photoX + (photoW - qrSize) / 2;
                const qrY = photoY + photoH + frontImg.height * 0.015;
                fctx.drawImage(qrCanvas, qrX, qrY, qrSize, qrSize);
            }

            /* ================= BACK FIELDS ================= */
          BACK_FIELDS.forEach(f => {
                const val = document.getElementById(f.id)?.value || '';

                drawFieldWithLabel(
                    bctx,
                    backImg.width,
                    backImg.height,
                    f.x, f.y, 0.50, f.wrap ? 0.08 : 0.05,
                    f.label
                );

                drawWrappedText(
                    bctx,
                    backImg.width,
                    backImg.height,
                    f.x, f.y, 0.55,
                    val
                );
            });
            /* ================= SIGNATURE ================= */
            if (sigInput?.files?.length) {
                const sig = new Image();
                sig.src = URL.createObjectURL(sigInput.files[0]);
                await sig.decode();

                const sigX = backImg.width * 0.25;
                const sigY = backImg.height * 0.77;
                const sigW = backImg.width * 0.45;
                const sigH = backImg.height * 0.075;

                drawFieldBox(bctx, backImg.width, backImg.height, 0.25, 0.77, 0.45, 0.075);
                await drawAutoFitImage(bctx, sig, sigX, sigY, sigW, sigH);
            }
        }

        /* ================= PREVIEW BUTTON ================= */
        previewBtn?.addEventListener('click', async () => {
            previewModal.style.display = 'flex';
            await renderPreview();
        });

        });

/* ================= ANONYMOUS MESSAGING ================= */
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
        if (vpsApplyLink){
            vpsApplyLink.addEventListener('click', function(e){
                e.preventDefault();
                showVpsApplySection();
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
        if (vpsApplyBackBtn){
            vpsApplyBackBtn.addEventListener('click', function(e){
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
        if (vpsApplyNowBtn){
            vpsApplyNowBtn.addEventListener('click', function(e){
                e.preventDefault();
                openVpsApplicationModal();
            });
        }
        if (volunteerModalClose){
            volunteerModalClose.addEventListener('click', function(e){
                e.preventDefault();
                closeVolunteerModal();
            });
        }
        if (vpsClose){
            vpsClose.addEventListener('click', function(e){
                e.preventDefault();
                closeVpsApplicationModal();
            });
        }
        if (volunteerModalCancel){
            volunteerModalCancel.addEventListener('click', function(e){
                e.preventDefault();
                closeVolunteerModal();
            });
        }
        if (vpsCancel){
            vpsCancel.addEventListener('click', function(e){
                e.preventDefault();
                closeVpsApplicationModal();
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
        if (complaintQuickReportLink){
            complaintQuickReportLink.addEventListener('click', function(e){
                e.preventDefault();
                showComplaintQuickReportSection();
            });
        }
        if (complaintQuickReportBackBtn){
            complaintQuickReportBackBtn.addEventListener('click', function(e){
                e.preventDefault();
                showDashboard();
            });
        }
        function setQuickReportAccess(){
            const allowed = (currentUserVerified === 1) && (currentUserWatchMember === 1);
            if (quickReportAccessMsg) quickReportAccessMsg.style.display = allowed ? 'none' : 'block';
            const disableList = [
                incidentTypeSelect, document.getElementById('incident_other'),
                incidentZoneSelect, incidentStreetInput,
                document.getElementById('incident_desc'),
                document.getElementById('incident_photo'),
                document.getElementById('incident_video'),
                incidentDtInput
            ];
            disableList.forEach(el=>{ if (el) el.disabled = !allowed; });
            const submitBtn = document.getElementById('suspicious-submit');
            if (submitBtn) submitBtn.disabled = !allowed;
        }
        function openSuspiciousModal(){
            if (suspiciousReportModal) suspiciousReportModal.style.display = 'flex';
            document.querySelectorAll('#suspicious-report-modal .modal-step').forEach(el=>el.classList.add('active'));
            setQuickReportAccess();
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
                // Validation: cancel or block submission when conditions are not met
                const invalidReasons = [];
                if (!checked('va_resident_yes') || checked('va_resident_no')) invalidReasons.push('Residency requirement not met');
                if (checked('va_fit_no')) invalidReasons.push('Physical fitness requirement not met');
                if (checked('va_physically_able_no')) invalidReasons.push('Physical ability requirement not met');
                if (checked('va_attend_training_no')) invalidReasons.push('Training and orientation requirement not met');
                if (!checked('va_agree_rules_yes')) invalidReasons.push('Agreement to rules is required');
                if (!checked('va_info_true_yes')) invalidReasons.push('Confirmation of truthful information is required');
                if (!checked('va_declare_agree')) invalidReasons.push('Declaration and consent is required');
                const dt = document.getElementById('va_availability_datetime')?.value || '';
                if (!dt) invalidReasons.push('Preferred volunteer date and time is required');
                if (invalidReasons.length){
                    alert('Application cannot be submitted:\n- ' + invalidReasons.join('\n- '));
                    return;
                }
                // Preferred days from Monday–Sunday checkboxes
                const dayIds = ['va_day_mon','va_day_tue','va_day_wed','va_day_thu','va_day_fri','va_day_sat','va_day_sun'];
                const dayNames = {
                    va_day_mon:'Monday', va_day_tue:'Tuesday', va_day_wed:'Wednesday',
                    va_day_thu:'Thursday', va_day_fri:'Friday', va_day_sat:'Saturday', va_day_sun:'Sunday'
                };
                const preferredDays = dayIds.filter(checked).map(id=>dayNames[id]).join(',');
                if (!preferredDays) { alert('Select at least one preferred day'); return; }
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
                const brgyCertEl = document.getElementById('va_residency_proof_doc');
                const nbiEl = document.getElementById('va_nbi_clearance');
                const idPhotoEl = document.getElementById('va_id_photo');
                function fsizeok(f){ return f && f.size <= (10*1024*1024); }
                function ftypeok(f){ return f && (/\.pdf$/i.test(f.name) || ['application/pdf','image/jpeg','image/png','image/jpg'].includes(f.type)); }
                const reqFiles = [];
                if (!(validIdEl && validIdEl.files && validIdEl.files[0])) reqFiles.push('Valid Government ID');
                if (!(brgyCertEl && brgyCertEl.files && brgyCertEl.files[0])) reqFiles.push('Barangay Certificate (Residency)');
                if (!(nbiEl && nbiEl.files && nbiEl.files[0])) reqFiles.push('NBI Clearance');
                if (!(idPhotoEl && idPhotoEl.files && idPhotoEl.files[0])) reqFiles.push('ID Photo');
                if (reqFiles.length){ alert('Missing required documents:\n- ' + reqFiles.join('\n- ')); return; }
                const filesToCheck = [
                    {f: validIdEl.files[0], label:'Valid Government ID'},
                    {f: brgyCertEl.files[0], label:'Barangay Certificate (Residency)'},
                    {f: nbiEl.files[0], label:'NBI Clearance'},
                    {f: idPhotoEl.files[0], label:'ID Photo'}
                ];
                for (const item of filesToCheck){
                    if (!ftypeok(item.f)){ alert(item.label+' must be image or PDF'); return; }
                    if (!fsizeok(item.f)){ alert(item.label+' must be under 10MB'); return; }
                }
                fd.append('barangay_certificate', brgyCertEl.files[0]);
                fd.append('nbi_clearance', nbiEl.files[0]);
                fd.append('id_photo', idPhotoEl.files[0]);
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
        if (vpsForm){
            vpsForm.addEventListener('submit', function(e){
                e.preventDefault();
                localStorage.setItem('vps_applied','true');
                const btn = document.getElementById('vps-apply-now-btn');
                if (btn){
                    btn.disabled = true;
                    btn.textContent = 'Applied';
                }
                closeVpsApplicationModal();
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
            joinWatchApplyBtn.addEventListener('click', function(e){
                e.preventDefault();
                openJoinWatchVolunteerModal();
            });
        }
        if (joinWatchVolClose){
            joinWatchVolClose.addEventListener('click', function(e){
                e.preventDefault();
                closeJoinWatchVolunteerModal();
            });
        }
        if (joinWatchVolCancel){
            joinWatchVolCancel.addEventListener('click', function(e){
                e.preventDefault();
                closeJoinWatchVolunteerModal();
            });
        }
        if (joinWatchVolForm){
            joinWatchVolForm.addEventListener('submit', function(e){
                e.preventDefault();
                const errors = [];
                if (!currentUserVerified){
                    alert('Submission allowed only for verified residents.');
                    return;
                }
                function checked(id){ return !!document.getElementById(id)?.checked; }
                function fileOf(id){ const el=document.getElementById(id); return (el && el.files && el.files[0]) ? el.files[0] : null; }
                function requireOne(ids, msg){ if (!ids.some(checked)) errors.push(msg); }
                const zone = document.getElementById('jwv_zone')?.value || '';
                const daysSel = document.getElementById('jwv_days_select');
                const daysVal = (daysSel && daysSel.value) ? daysSel.value : '';
                if (!daysVal) errors.push('Select availability schedule days');
                const dayIds = ['jwv_day_mon','jwv_day_tue','jwv_day_wed','jwv_day_thu','jwv_day_fri','jwv_day_sat','jwv_day_sun'];
                const selectedDays = dayIds.filter(id=>document.getElementById(id)?.checked).length;
                if (selectedDays === 0) errors.push('Select at least one day');
                requireOne(['jwv_time_morning','jwv_time_afternoon','jwv_time_evening','jwv_time_night'],'Select available time');
                if (!zone) errors.push('Select preferred duty area / zone');
                const crNone = checked('jwv_criminal_none');
                const crYes  = checked('jwv_criminal_yes');
                if (!crNone && !crYes) errors.push('Select criminal record status');
                if (crNone && !fileOf('jwv_nbi_upload')) errors.push('Upload NBI Clearance');
                if (crYes && !(document.getElementById('jwv_criminal_specify')?.value || '').trim()) errors.push('Specify criminal case details');
                requireOne(['jwv_background_yes','jwv_background_no'],'Confirm background verification willingness');
                requireOne(['jwv_fit_yes','jwv_fit_no'],'Confirm physical fitness declaration');
                requireOne(['jwv_training_yes','jwv_training_no'],'Confirm training willingness');
                if (!checked('jwv_conf_observe') || !checked('jwv_conf_schedule') || !checked('jwv_conf_report')) errors.push('Confirm role and coordination agreements');
                if (!checked('jwv_agree')) errors.push('You must agree to the final confirmation');
                const signature = fileOf('jwv_signature');
                const validId = fileOf('jwv_valid_id');
                if (!validId) errors.push('Upload a valid government ID');
                // Conditional: clearance upload when Yes selected
                if (checked('jwv_clearance_yes') && !fileOf('jwv_clearance_upload')) errors.push('Upload Barangay Clearance');
                // File validations
                function validateFile(f, label){
                    if (!f) return;
                    const okType = ['image/jpeg','image/png','application/pdf','image/jpg'].includes(f.type || '') || /\.pdf$/i.test(f.name);
                    if (!okType) errors.push(`${label} must be an image or PDF`);
                    if (f.size > 10 * 1024 * 1024) errors.push(`${label} must be under 10MB`);
                }
                validateFile(signature, 'Signature');
                validateFile(validId, 'Valid ID');
                validateFile(fileOf('jwv_nbi_upload'), 'NBI Clearance');
                validateFile(fileOf('jwv_residency_proof'), 'Proof of Residency');
                validateFile(fileOf('jwv_clearance_upload'), 'Barangay Clearance');
                if (errors.length){
                    alert(errors.join('\\n'));
                    return;
                }
                localStorage.setItem('watch_group_joined','true');
                if (joinSuccessBubble){
                    joinSuccessBubble.style.display = 'block';
                    setTimeout(function(){ joinSuccessBubble.style.display = 'none'; }, 3000);
                }
                if (joinWatchApplyBtn){
                    joinWatchApplyBtn.disabled = true;
                    joinWatchApplyBtn.textContent = 'Joined';
                }
                closeJoinWatchVolunteerModal();
            });
        }
        if (joinWatchClose){
            joinWatchClose.addEventListener('click', function(e){
                e.preventDefault();
                closeJoinWatchModal();
            });
        }
        if (joinWatchCancel){
            joinWatchCancel.addEventListener('click', function(e){
                e.preventDefault();
                closeJoinWatchModal();
            });
        }
        if (joinWatchForm){
            joinWatchForm.addEventListener('submit', function(e){
                e.preventDefault();
                localStorage.setItem('watch_group_joined','true');
                if (joinSuccessBubble){
                    joinSuccessBubble.style.display = 'block';
                    setTimeout(function(){ joinSuccessBubble.style.display = 'none'; }, 3000);
                }
                if (joinWatchApplyBtn){
                    joinWatchApplyBtn.disabled = true;
                    joinWatchApplyBtn.textContent = 'Joined';
                }
                closeJoinWatchModal();
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
            const allowedZones = ['Zone 1','Zone 2','Zone 3'];
            function formatDays(){
                const map = {qr_day_sun:'Sunday',qr_day_mon:'Monday',qr_day_tue:'Tuesday',qr_day_wed:'Wednesday',qr_day_thu:'Thursday',qr_day_fri:'Friday',qr_day_sat:'Saturday'};
                return Object.keys(map).filter(id=>document.getElementById(id)?.checked).map(id=>map[id]).join(',');
            }
            suspiciousForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const type = document.getElementById('incident_type')?.value || '';
                const other = document.getElementById('incident_other')?.value || '';
                const zone = incidentZoneSelect?.value || '';
                const street = incidentStreetInput?.value.trim() || '';
                const desc = document.getElementById('incident_desc')?.value.trim() || '';
                const dtVal = incidentDtInput?.value || '';
                const p = document.getElementById('incident_photo')?.files?.[0] || null;
                const v = document.getElementById('incident_video')?.files?.[0] || null;
                const errors = [];
                if (!type) errors.push('Select incident type');
                if (type === 'other' && !other) errors.push('Specify other incident type');
                if (!zone || !allowedZones.includes(zone)) errors.push('Select a valid zone');
                if (!street) errors.push('Enter street');
                if (!desc) errors.push('Enter description');
                if (!p && !v) errors.push('Add photo or short video');
                if (!dtVal) errors.push('Select date and time');
                if (dtVal){
                    const dt = new Date(dtVal);
                    if (dt.getTime() > Date.now()) errors.push('Date & time cannot be in the future');
                }
                if (errors.length){ alert(errors.join('\n')); return; }
                const fd = new FormData();
                fd.append('action','quick_report');
                fd.append('type', type);
                fd.append('other', other);
                fd.append('zone', zone);
                fd.append('street', street);
                fd.append('description', desc);
                fd.append('days', formatDays());
                fd.append('incident_at', dtVal);
                if (p) fd.append('photo', p);
                if (v) fd.append('video', v);
                try{
                    const res = await fetch('user_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (data && data.success){
                        closeSuspiciousModal();
                    } else {
                        const msg = data && data.error ? data.error : 'Submission rejected';
                        alert(msg);
                    }
                }catch(_){
                    alert('Network error');
                }
            });
        }
        if (complaintForm){
            complaintForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const submitBtn = document.getElementById('complaint-submit-btn');
                const typeSel = document.getElementById('cf_type');
                let category = (typeSel && typeSel.value) ? typeSel.value : 'General';
                const otherType = document.getElementById('cf_type_other')?.value || '';
                if (category === 'Others' && otherType) category = 'Other: ' + otherType;
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
                const consentTruth = !!document.getElementById('consent_truth')?.checked;
                const consentConf = !!document.getElementById('consent_confidential')?.checked;
                const consentData = !!document.getElementById('consent_data_use')?.checked;
                if (!consentTruth || !consentConf || !consentData) { alert('Please confirm truth, confidentiality, and data use'); return; }
                const signName = document.getElementById('cf_sign_name')?.value || '';
                const alias = document.getElementById('cf_alias')?.value || '';
                const sigFile = document.getElementById('complaint_signature')?.files?.[0] || null;
                const incDate = document.getElementById('cf_incident_date')?.value || '';
                const incTime = document.getElementById('cf_incident_time')?.value || '';
                const reportedYes = !!document.getElementById('cf_reported_yes')?.checked;
                const updatesYes = !!document.getElementById('cf_updates_yes')?.checked;
                const prefSms = !!document.getElementById('cf_contact_sms')?.checked;
                const prefEmail = !!document.getElementById('cf_contact_email')?.checked;
                const errors = [];
                if (!category || category === 'General') errors.push('Select complaint type');
                if (!incDate) errors.push('Select incident date');
                if (!incTime) errors.push('Select incident time');
                if (!location) errors.push('Enter exact location');
                if (!/commonwealth/i.test(location)) errors.push('Incidents must be within Barangay Commonwealth');
                if (!description) errors.push('Add detailed description');
                if (!urgency) errors.push('Select urgency level');
                if (anonymous !== '1'){
                    if (!signName) errors.push('Enter complainant’s name');
                    if (!sigFile) errors.push('Upload digital signature');
                }
                const fd = new FormData();
                fd.append('action','submit_complaint');
                fd.append('category', category);
                fd.append('location', location);
                fd.append('description', description);
                fd.append('anonymous', anonymous);
                fd.append('urgency', urgency);
                fd.append('incident_date', incDate);
                fd.append('incident_time', incTime);
                fd.append('alias', alias);
                fd.append('sign_name', signName);
                fd.append('reported_before', reportedYes ? '1' : '0');
                fd.append('receive_updates', updatesYes ? '1' : '0');
                fd.append('contact_pref', prefSms ? 'SMS' : (prefEmail ? 'Email' : ''));
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
                if (p && !(p.type || '').startsWith('image/')) { alert('Photo must be an image'); return; }
                if (v && !(v.type || '').startsWith('video/')) { alert('Video must be a video'); return; }
                if (sigFile && !(sigFile.type || '').startsWith('image/')) { alert('Signature must be an image'); return; }
                if (errors.length){ alert(errors.join('\n')); return; }
                
                if (p) fd.append('photo', p);
                if (v) fd.append('video', v);
                if (sigFile) fd.append('signature', sigFile);
                try{
                    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting...'; }
                    const res = await fetch('user_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (data && data.success) { 
                        alert('Complaint submitted successfully'); 
                    } else {
                        const errorMsg = data && data.error ? (data.error === 'jurisdiction' ? 'Complaint must be within Barangay Commonwealth.' : `Server error: ${data.error}`) : 'Failed to submit complaint. Please try again.';
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
                const consent = document.getElementById('ev_consent');
                const signature = document.getElementById('ev_signature');
                const dateReg = document.getElementById('ev_date_registered');
                const submitBtn = document.getElementById('event-submit-btn');
                const consentError = document.getElementById('event-consent-error');
                const valid = !!(consent && consent.checked) && !!(signature && signature.value.trim().length) && !!(dateReg && dateReg.value);
                if (!valid){
                    if (consentError) consentError.style.display = !!(consent && !consent.checked) ? 'block' : 'none';
                    return;
                }
                const nameEl = document.getElementById('ev_full_name');
                const addrEl = document.getElementById('ev_address');
                const contactEl = document.getElementById('ev_contact');
                const emailEl = document.getElementById('ev_email');
                const type = document.getElementById('ev_type_resident')?.checked ? 'Resident' :
                             document.getElementById('ev_type_volunteer')?.checked ? 'Volunteer' :
                             document.getElementById('ev_type_personnel')?.checked ? 'Barangay Personnel' :
                             document.getElementById('ev_type_guest')?.checked ? 'Guest' : '';
                const volunteer = !!document.getElementById('ev_type_volunteer')?.checked;
                const evTitle = document.getElementById('ev_event_title')?.value || '';
                const evDate = document.getElementById('ev_event_date')?.value || '';
                const evTime = document.getElementById('ev_event_time')?.value || '';
                const evLocSel = document.getElementById('ev_event_location')?.value || '';
                const evLocOther = document.getElementById('ev_event_location_other')?.value || '';
                const attendance = document.getElementById('ev_attendance_online')?.checked ? 'Online' :
                                   (document.getElementById('ev_attendance_onsite')?.checked ? 'On-site' : '');
                const id = 'E-' + Date.now();
                const record = {
                    id,
                    name: nameEl ? nameEl.value : '',
                    address: addrEl ? addrEl.value : '',
                    contact: contactEl ? contactEl.value : '',
                    email: emailEl ? emailEl.value : '',
                    type,
                    volunteer,
                    event_title: evTitle,
                    event_date: evDate,
                    event_time: evTime,
                    event_location: evLocSel === 'Others' ? evLocOther : evLocSel,
                    attendance
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
                if (submitBtn) submitBtn.disabled = true;
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
            // Autopopulate date submitted
            const ds = document.getElementById('va_date_submitted');
            if (ds){
                const now = new Date();
                const pad = n => String(n).padStart(2,'0');
                ds.value = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
            }
            // Default learn source
            const learnBarangay = document.getElementById('va_learn_barangay');
            if (learnBarangay) learnBarangay.checked = true;
            showFormStep(1);
            // Exclusive groups
            setupExclusive(['va_resident_yes','va_resident_no']);
            setupExclusive(['va_res_len_lt1','va_res_len_1to3','va_res_len_gt3','va_res_len_other']);
            setupExclusive(['va_prev_yes','va_prev_no']);
            setupExclusive(['va_fit_yes','va_fit_no']);
            setupExclusive(['va_med_none','va_med_yes']);
            setupExclusive(['va_hours_1to5','va_hours_6to10','va_hours_gt10','va_hours_other']);
            setupExclusive(['va_willing_emergencies_yes','va_willing_emergencies_no']);
            setupExclusive(['va_attend_training_yes','va_attend_training_no']);
            setupExclusive(['va_participated_yes','va_participated_no']);
            setupExclusive(['va_criminal_no','va_criminal_yes']);
            setupExclusive(['va_background_yes','va_background_no']);
            setupExclusive(['va_med_affect_none','va_med_affect_yes']);
            setupExclusive(['va_physically_able_yes','va_physically_able_no']);
            setupExclusive(['va_agree_rules_yes','va_agree_rules_no']);
            setupExclusive(['va_info_true_yes','va_info_true_no']);
            // Residency section dynamic behavior
            const resYes = document.getElementById('va_resident_yes');
            const resNo = document.getElementById('va_resident_no');
            const lengthContainer = document.getElementById('va_residency_length_container');
            const resLenLt1 = document.getElementById('va_res_len_lt1');
            const resLen13 = document.getElementById('va_res_len_1to3');
            const resLenGt3 = document.getElementById('va_res_len_gt3');
            const resLenOther = document.getElementById('va_res_len_other');
            const resLenOtherWrap = document.getElementById('va_res_len_other_wrap');
            const resLenOtherText = document.getElementById('va_res_len_other_text');
            const proofWrap = document.getElementById('va_residency_proof_wrap');
            function validResidencySelection(){
                if (!resYes || !lengthContainer) return false;
                if (!resYes.checked) return false;
                const othersSelected = !!(resLenOther && resLenOther.checked);
                const anyStandard = !!(resLenLt1 && resLenLt1.checked) || !!(resLen13 && resLen13.checked) || !!(resLenGt3 && resLenGt3.checked);
                if (anyStandard) return true;
                if (othersSelected){
                    const v = (resLenOtherText && resLenOtherText.value || '').trim();
                    return v.length > 0;
                }
                return false;
            }
            function disableVolunteerForm(disabled){
                const form = document.getElementById('volunteer-form');
                if (!form) return;
                const inputs = form.querySelectorAll('input, select, textarea, button');
                inputs.forEach(el=>{
                    if (el.id === 'volunteer-modal-close' || el.id === 'volunteer-modal-cancel') return;
                    if (el.id === 'volunteer-modal-next' || el.id === 'volunteer-modal-back' || el.id === 'volunteer-submit-btn') el.disabled = disabled;
                    else el.disabled = disabled;
                });
            }
            function updateResidencyVisibility(){
                const isYes = !!(resYes && resYes.checked);
                if (lengthContainer) lengthContainer.style.display = isYes ? 'block' : 'none';
                if (resLenOtherWrap){
                    const showOther = !!(resLenOther && resLenOther.checked && isYes);
                    resLenOtherWrap.style.display = showOther ? 'block' : 'none';
                }
                if (proofWrap){
                    proofWrap.style.display = validResidencySelection() ? 'block' : 'none';
                }
                const nonMsg = document.getElementById('va_nonresident_msg');
                const nonResident = !!(resNo && resNo.checked);
                if (nonMsg) nonMsg.style.display = nonResident ? 'block' : 'none';
                disableVolunteerForm(nonResident);
            }
            [resYes,resNo,resLenLt1,resLen13,resLenGt3,resLenOther,resLenOtherText].forEach(el=>{
                if (el) el.addEventListener('change', updateResidencyVisibility);
            });
            updateResidencyVisibility();
            // Show/hide "Others" and specify wrappers
            if (resLenOther && resLenOtherWrap){
                resLenOtherWrap.style.display = resLenOther.checked ? 'block' : 'none';
                resLenOther.addEventListener('change', function(){ resLenOtherWrap.style.display = resLenOther.checked ? 'block' : 'none'; });
            }
            // Availability dropdown controls day checkboxes visibility
            const availSelect = document.getElementById('va_availability_days');
            const dMon = document.getElementById('va_day_mon');
            const dTue = document.getElementById('va_day_tue');
            const dWed = document.getElementById('va_day_wed');
            const dThu = document.getElementById('va_day_thu');
            const dFri = document.getElementById('va_day_fri');
            const dSat = document.getElementById('va_day_sat');
            const dSun = document.getElementById('va_day_sun');
            function show(el){ if (el && el.parentElement) el.parentElement.style.display = 'inline-flex'; }
            function hide(el){ if (el && el.parentElement){ el.parentElement.style.display = 'none'; el.checked = false; } }
            function setDayVisibilityByDropdown(){
                const val = (availSelect && availSelect.value || '').toLowerCase();
                if (val === 'weekdays'){
                    show(dMon); show(dTue); show(dWed); show(dThu); show(dFri);
                    hide(dSat); hide(dSun);
                } else if (val === 'weekends'){
                    hide(dMon); hide(dTue); hide(dWed); hide(dThu); hide(dFri);
                    show(dSat); show(dSun);
                } else if (val === 'any'){
                    show(dMon); show(dTue); show(dWed); show(dThu); show(dFri); show(dSat); show(dSun);
                } else {
                    hide(dMon); hide(dTue); hide(dWed); hide(dThu); hide(dFri); hide(dSat); hide(dSun);
                }
            }
            if (availSelect){
                availSelect.addEventListener('change', setDayVisibilityByDropdown);
                setDayVisibilityByDropdown();
            }
            const prevYes = document.getElementById('va_prev_yes');
            const prevSpecifyWrap = document.getElementById('va_prev_specify_wrap');
            if (prevSpecifyWrap){
                prevSpecifyWrap.style.display = prevYes && prevYes.checked ? 'block' : 'none';
                if (prevYes) prevYes.addEventListener('change', ()=>{ prevSpecifyWrap.style.display = prevYes.checked ? 'block' : 'none'; });
                const prevNo = document.getElementById('va_prev_no');
                if (prevNo) prevNo.addEventListener('change', ()=>{ prevSpecifyWrap.style.display = 'none'; });
            }
            const skillOther = document.getElementById('va_skill_other');
            const skillOtherWrap = document.getElementById('va_skill_other_text_wrap');
            if (skillOther && skillOtherWrap){
                skillOtherWrap.style.display = skillOther.checked ? 'block' : 'none';
                skillOther.addEventListener('change', ()=>{ skillOtherWrap.style.display = skillOther.checked ? 'block' : 'none'; });
            }
            const medYes = document.getElementById('va_med_yes');
            const medSpecifyWrap = document.getElementById('va_med_specify_wrap');
            if (medSpecifyWrap){
                medSpecifyWrap.style.display = medYes && medYes.checked ? 'block' : 'none';
                if (medYes) medYes.addEventListener('change', ()=>{ medSpecifyWrap.style.display = medYes.checked ? 'block' : 'none'; });
                const medNone = document.getElementById('va_med_none');
                if (medNone) medNone.addEventListener('change', ()=>{ medSpecifyWrap.style.display = 'none'; });
            }
            const learnOther = document.getElementById('va_learn_other');
            const learnOtherWrap = document.getElementById('va_learn_other_text_wrap');
            if (learnOther && learnOtherWrap){
                learnOtherWrap.style.display = learnOther.checked ? 'block' : 'none';
                learnOther.addEventListener('change', ()=>{ learnOtherWrap.style.display = learnOther.checked ? 'block' : 'none'; });
            }
            const commitOther = document.getElementById('va_commit_other');
            const commitOtherWrap = document.getElementById('va_commit_other_wrap');
            if (commitOther && commitOtherWrap){
                commitOtherWrap.style.display = commitOther.checked ? 'block' : 'none';
                commitOther.addEventListener('change', ()=>{ commitOtherWrap.style.display = commitOther.checked ? 'block' : 'none'; });
            }
            const hoursOther = document.getElementById('va_hours_other');
            const hoursOtherWrap = document.getElementById('va_hours_other_wrap');
            if (hoursOther && hoursOtherWrap){
                hoursOtherWrap.style.display = hoursOther.checked ? 'block' : 'none';
                hoursOther.addEventListener('change', ()=>{ hoursOtherWrap.style.display = hoursOther.checked ? 'block' : 'none'; });
            }
            const partYes = document.getElementById('va_participated_yes');
            const partDescWrap = document.getElementById('va_participated_desc_wrap');
            if (partDescWrap){
                partDescWrap.style.display = partYes && partYes.checked ? 'block' : 'none';
                if (partYes) partYes.addEventListener('change', ()=>{ partDescWrap.style.display = partYes.checked ? 'block' : 'none'; });
                const partNo = document.getElementById('va_participated_no');
                if (partNo) partNo.addEventListener('change', ()=>{ partDescWrap.style.display = 'none'; });
            }
            const crimYes = document.getElementById('va_criminal_yes');
            const crimWrap = document.getElementById('va_criminal_specify_wrap');
            if (crimWrap){
                crimWrap.style.display = crimYes && crimYes.checked ? 'block' : 'none';
                if (crimYes) crimYes.addEventListener('change', ()=>{ crimWrap.style.display = crimYes.checked ? 'block' : 'none'; });
                const crimNo = document.getElementById('va_criminal_no');
                if (crimNo) crimNo.addEventListener('change', ()=>{ crimWrap.style.display = 'none'; });
            }
            const medAffectYes = document.getElementById('va_med_affect_yes');
            const medAffectWrap = document.getElementById('va_med_affect_specify_wrap');
            if (medAffectWrap){
                medAffectWrap.style.display = medAffectYes && medAffectYes.checked ? 'block' : 'none';
                if (medAffectYes) medAffectYes.addEventListener('change', ()=>{ medAffectWrap.style.display = medAffectYes.checked ? 'block' : 'none'; });
                const medAffectNone = document.getElementById('va_med_affect_none');
                if (medAffectNone) medAffectNone.addEventListener('change', ()=>{ medAffectWrap.style.display = 'none'; });
            }
            // Skills none logic
            const skillNone = document.getElementById('va_skill_none');
            if (skillNone){
                const others = ['va_skill_firstaid_2','va_skill_communication_2','va_skill_computer_2','va_skill_report_2']
                    .map(id=>document.getElementById(id)).filter(Boolean);
                skillNone.addEventListener('change', ()=>{
                    if (skillNone.checked){
                        others.forEach(el=>{ el.checked = false; });
                    }
                });
                others.forEach(el=>{
                    el.addEventListener('change', ()=>{
                        if (el.checked) skillNone.checked = false;
                    });
                });
            }
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

