<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_resolution'])) {
    header('Content-Type: application/json');
    $no = trim($_POST['resolution_no'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $cat = trim($_POST['category'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($title === '') { echo json_encode(['ok'=>false,'error'=>'missing_title']); exit(); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_resolutions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            resolution_no VARCHAR(100),
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100),
            description TEXT,
            adopted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('approved','draft') DEFAULT 'approved'
        )");
        $stmtI = $pdo->prepare("INSERT INTO barangay_resolutions (resolution_no, title, category, description, status) VALUES (?, ?, ?, ?, 'approved')");
        $stmtI->execute([$no, $title, $cat, $desc]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'resolution_no'=>$no,
            'title'=>$title,
            'category'=>$cat,
            'adopted_at'=>date('Y-m-d H:i:s'),
            'status'=>'approved'
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_clearance'])) {
    header('Content-Type: application/json');
    $no = trim($_POST['clearance_no'] ?? '');
    $name = trim($_POST['resident_name'] ?? '');
    $addr = trim($_POST['resident_address'] ?? '');
    $ver = trim($_POST['verified_no_issues'] ?? 'yes');
    $issuedBy = trim($_POST['issued_by'] ?? '');
    $issuedDate = trim($_POST['date_issued'] ?? '');
    if ($name === '') { echo json_encode(['ok'=>false,'error'=>'missing_resident']); exit(); }
    if ($issuedDate === '') { $issuedDate = date('Y-m-d'); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_clearances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            clearance_no VARCHAR(100),
            resident_name VARCHAR(255) NOT NULL,
            resident_address VARCHAR(255),
            verified_no_issues ENUM('yes','no') DEFAULT 'yes',
            issued_by VARCHAR(255),
            issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmtI = $pdo->prepare("INSERT INTO barangay_clearances (clearance_no, resident_name, resident_address, verified_no_issues, issued_by) VALUES (?, ?, ?, ?, ?)");
        $stmtI->execute([$no, $name, $addr, ($ver==='no'?'no':'yes'), $issuedBy]);
        $stmtI = null;
        $pdo->exec("CREATE TABLE IF NOT EXISTS issued_document_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_type VARCHAR(100),
            doc_no VARCHAR(100),
            recipient_name VARCHAR(255),
            date_issued DATE,
            issued_by VARCHAR(255),
            logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_doc (doc_type, doc_no)
        )");
        $stmtL = $pdo->prepare("INSERT IGNORE INTO issued_document_logs (doc_type, doc_no, recipient_name, date_issued, issued_by) VALUES ('Barangay Clearance', ?, ?, ?, ?)");
        $stmtL->execute([$no, $name, $issuedDate, $issuedBy]);
        $stmtL = null;
        echo json_encode(['ok'=>true,'record'=>[
            'clearance_no'=>$no,
            'resident_name'=>$name,
            'resident_address'=>$addr,
            'verified_no_issues'=>($ver==='no'?'no':'yes'),
            'issued_at'=>date('Y-m-d H:i:s'),
            'issued_by'=>$issuedBy
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_residency'])) {
    header('Content-Type: application/json');
    $no = trim($_POST['certificate_no'] ?? '');
    $name = trim($_POST['resident_name'] ?? '');
    $addr = trim($_POST['resident_address'] ?? '');
    $start = trim($_POST['start_date'] ?? '');
    $end = trim($_POST['end_date'] ?? '');
    $issuedBy = trim($_POST['issued_by'] ?? '');
    $issuedDate = trim($_POST['date_issued'] ?? '');
    if ($name === '') { echo json_encode(['ok'=>false,'error'=>'missing_resident']); exit(); }
    if ($issuedDate === '') { $issuedDate = date('Y-m-d'); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS residency_certificates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            certificate_no VARCHAR(100),
            resident_name VARCHAR(255) NOT NULL,
            resident_address VARCHAR(255),
            start_date DATE,
            end_date DATE,
            issued_by VARCHAR(255),
            issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmtI = $pdo->prepare("INSERT INTO residency_certificates (certificate_no, resident_name, resident_address, start_date, end_date, issued_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtI->execute([$no, $name, $addr, ($start!==''?$start:null), ($end!==''?$end:null), $issuedBy]);
        $stmtI = null;
        $pdo->exec("CREATE TABLE IF NOT EXISTS issued_document_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_type VARCHAR(100),
            doc_no VARCHAR(100),
            recipient_name VARCHAR(255),
            date_issued DATE,
            issued_by VARCHAR(255),
            logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_doc (doc_type, doc_no)
        )");
        $stmtL = $pdo->prepare("INSERT IGNORE INTO issued_document_logs (doc_type, doc_no, recipient_name, date_issued, issued_by) VALUES ('Residency Certificate', ?, ?, ?, ?)");
        $stmtL->execute([$no, $name, $issuedDate, $issuedBy]);
        $stmtL = null;
        echo json_encode(['ok'=>true,'record'=>[
            'certificate_no'=>$no,
            'resident_name'=>$name,
            'resident_address'=>$addr,
            'start_date'=>$start,
            'end_date'=>$end,
            'issued_at'=>date('Y-m-d H:i:s'),
            'issued_by'=>$issuedBy
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_issued_log'])) {
    header('Content-Type: application/json');
    $type = trim($_POST['doc_type'] ?? '');
    $no = trim($_POST['doc_no'] ?? '');
    $rec = trim($_POST['recipient_name'] ?? '');
    $issuedBy = trim($_POST['issued_by'] ?? '');
    $issuedDate = trim($_POST['date_issued'] ?? '');
    if ($type === '' || $rec === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit(); }
    if ($issuedDate === '') { $issuedDate = date('Y-m-d'); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS issued_document_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_type VARCHAR(100),
            doc_no VARCHAR(100),
            recipient_name VARCHAR(255),
            date_issued DATE,
            issued_by VARCHAR(255),
            logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_doc (doc_type, doc_no)
        )");
        $stmtI = $pdo->prepare("INSERT INTO issued_document_logs (doc_type, doc_no, recipient_name, date_issued, issued_by) VALUES (?, ?, ?, ?, ?)");
        $stmtI->execute([$type, $no, $rec, $issuedDate, $issuedBy]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'doc_type'=>$type,
            'doc_no'=>$no,
            'recipient_name'=>$rec,
            'date_issued'=>$issuedDate,
            'issued_by'=>$issuedBy,
            'logged_at'=>date('Y-m-d H:i:s')
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['route_document'])) {
    header('Content-Type: application/json');
    $docno = trim($_POST['doc_no'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['doc_type'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    if ($title === '' || $type === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit(); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS document_routing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_no VARCHAR(100),
            title VARCHAR(255) NOT NULL,
            doc_type VARCHAR(100),
            routed_to VARCHAR(255) DEFAULT 'Barangay Captain',
            routed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('Pending','Approved','Returned','Rejected') DEFAULT 'Pending',
            remarks TEXT
        )");
        $stmtI = $pdo->prepare("INSERT INTO document_routing (doc_no, title, doc_type, remarks) VALUES (?, ?, ?, ?)");
        $stmtI->execute([$docno, $title, $type, $remarks]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'doc_no'=>$docno,
            'title'=>$title,
            'doc_type'=>$type,
            'routed_to'=>'Barangay Captain',
            'routed_at'=>date('Y-m-d H:i:s'),
            'status'=>'Pending',
            'remarks'=>$remarks
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_signature'])) {
    header('Content-Type: application/json');
    $docno = trim($_POST['doc_no'] ?? '');
    $signer = trim($_POST['signer_name'] ?? '');
    $version = trim($_POST['doc_version'] ?? '');
    $auth = trim($_POST['auth_status'] ?? 'verified');
    if ($docno === '' || $signer === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit(); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS document_signatures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_no VARCHAR(100),
            signer_name VARCHAR(255) NOT NULL,
            signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            doc_version VARCHAR(50),
            auth_status ENUM('verified','unverified') DEFAULT 'verified'
        )");
        $stmtI = $pdo->prepare("INSERT INTO document_signatures (doc_no, signer_name, doc_version, auth_status) VALUES (?, ?, ?, ?)");
        $stmtI->execute([$docno, $signer, $version, ($auth==='unverified'?'unverified':'verified')]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'doc_no'=>$docno,
            'signer_name'=>$signer,
            'signed_at'=>date('Y-m-d H:i:s'),
            'doc_version'=>$version,
            'auth_status'=>($auth==='unverified'?'unverified':'verified')
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_approval_status'])) {
    header('Content-Type: application/json');
    $docno = trim($_POST['doc_no'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $allowed = ['Pending','Approved','Returned','Rejected'];
    if ($docno === '' || !in_array($status, $allowed, true)) { echo json_encode(['ok'=>false,'error'=>'invalid_input']); exit(); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS document_routing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_no VARCHAR(100),
            title VARCHAR(255) NOT NULL,
            doc_type VARCHAR(100),
            routed_to VARCHAR(255) DEFAULT 'Barangay Captain',
            routed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('Pending','Approved','Returned','Rejected') DEFAULT 'Pending',
            remarks TEXT
        )");
        $stmtU = $pdo->prepare("UPDATE document_routing SET status = ? WHERE doc_no = ?");
        $stmtU->execute([$status, $docno]);
        $stmtU = null;
        echo json_encode(['ok'=>true,'record'=>[
            'doc_no'=>$docno,
            'status'=>$status
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'update_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_audit_log'])) {
    header('Content-Type: application/json');
    $action = trim($_POST['action'] ?? '');
    $etype = trim($_POST['entity_type'] ?? '');
    $eid = trim($_POST['entity_id'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $actor = trim($_POST['actor_name'] ?? '');
    if ($action === '' || $etype === '' || $eid === '' || $actor === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit(); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(50),
            entity_type VARCHAR(100),
            entity_id VARCHAR(255),
            description TEXT,
            actor_name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmtI = $pdo->prepare("INSERT INTO audit_logs (action, entity_type, entity_id, description, actor_name) VALUES (?, ?, ?, ?, ?)");
        $stmtI->execute([$action, $etype, $eid, $desc, $actor]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'action'=>$action,
            'entity_type'=>$etype,
            'entity_id'=>$eid,
            'description'=>$desc,
            'actor_name'=>$actor,
            'created_at'=>date('Y-m-d H:i:s')
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_compliance'])) {
    header('Content-Type: application/json');
    $docno = trim($_POST['doc_no'] ?? '');
    $dtype = trim($_POST['doc_type'] ?? '');
    $req = trim($_POST['requirement'] ?? '');
    $act = trim($_POST['action_recommended'] ?? '');
    $status = trim($_POST['compliance_status'] ?? 'Compliant');
    $reviewedBy = trim($_POST['reviewed_by'] ?? '');
    if ($docno === '' || $dtype === '' || $req === '' || $act === '' || $reviewedBy === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit(); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS retention_compliance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_no VARCHAR(100),
            doc_type VARCHAR(100),
            requirement TEXT,
            action_recommended VARCHAR(100),
            compliance_status ENUM('Compliant','Non-compliant') DEFAULT 'Compliant',
            reviewed_by VARCHAR(255),
            reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmtI = $pdo->prepare("INSERT INTO retention_compliance (doc_no, doc_type, requirement, action_recommended, compliance_status, reviewed_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtI->execute([$docno, $dtype, $req, $act, ($status==='Non-compliant'?'Non-compliant':'Compliant'), $reviewedBy]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'doc_no'=>$docno,
            'doc_type'=>$dtype,
            'requirement'=>$req,
            'action_recommended'=>$act,
            'compliance_status'=>($status==='Non-compliant'?'Non-compliant':'Compliant'),
            'reviewed_by'=>$reviewedBy,
            'reviewed_at'=>date('Y-m-d H:i:s')
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_monthly_summary'])) {
    header('Content-Type: application/json');
    $year = (int)($_POST['year'] ?? date('Y'));
    $month = (int)($_POST['month'] ?? date('m'));
    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) { echo json_encode(['ok'=>false,'error'=>'invalid_input']); exit(); }
    $ym = sprintf('%04d-%02d', $year, $month);
    $res = 0; $cert = 0; $complaintsFiled = 0; $complaintsResolved = 0; $incidents = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM barangay_resolutions WHERE DATE_FORMAT(adopted_at, '%Y-%m') = ?");
        $stmt->execute([$ym]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $res = (int)($row['c'] ?? 0);
        $stmt = null;
    } catch (Exception $e) {}
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM barangay_clearances WHERE DATE_FORMAT(issued_at, '%Y-%m') = ?");
        $stmt->execute([$ym]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $clear = (int)($row['c'] ?? 0);
        $stmt = null;
    } catch (Exception $e) { $clear = 0; }
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM residency_certificates WHERE DATE_FORMAT(issued_at, '%Y-%m') = ?");
        $stmt->execute([$ym]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $resid = (int)($row['c'] ?? 0);
        $stmt = null;
    } catch (Exception $e) { $resid = 0; }
    $cert = $clear + $resid;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM incoming_communications WHERE DATE_FORMAT(date_received, '%Y-%m') = ? AND LOWER(subject) LIKE '%complaint%'");
        $stmt->execute([$ym]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $complaintsFiled = (int)($row['c'] ?? 0);
        $stmt = null;
    } catch (Exception $e) {}
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM communication_acknowledgments WHERE DATE_FORMAT(ack_date, '%Y-%m') = ? AND LOWER(subject) LIKE '%complaint%' AND status = 'acknowledged'");
        $stmt->execute([$ym]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $complaintsResolved = (int)($row['c'] ?? 0);
        $stmt = null;
    } catch (Exception $e) {}
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM outgoing_communications WHERE DATE_FORMAT(date_sent, '%Y-%m') = ? AND LOWER(subject) LIKE '%incident%'");
        $stmt->execute([$ym]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $incidents = (int)($row['c'] ?? 0);
        $stmt = null;
    } catch (Exception $e) {}
    echo json_encode(['ok'=>true,'record'=>[
        'year'=>$year,
        'month'=>$month,
        'resolutions'=>$res,
        'certificates'=>$cert,
        'complaints_filed'=>$complaintsFiled,
        'complaints_resolved'=>$complaintsResolved,
        'incidents_logged'=>$incidents
    ]]);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ordinance'])) {
    header('Content-Type: application/json');
    $no = trim($_POST['ordinance_no'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $cat = trim($_POST['category'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    if ($title === '') { echo json_encode(['ok'=>false,'error'=>'missing_title']); exit(); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_ordinances (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ordinance_no VARCHAR(100),
            title VARCHAR(255) NOT NULL,
            category VARCHAR(100),
            description TEXT,
            enacted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active','repealed') DEFAULT 'active'
        )");
        $stmtI = $pdo->prepare("INSERT INTO barangay_ordinances (ordinance_no, title, category, description, status) VALUES (?, ?, ?, ?, 'active')");
        $stmtI->execute([$no, $title, $cat, $desc]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'ordinance_no'=>$no,
            'title'=>$title,
            'category'=>$cat,
            'enacted_at'=>date('Y-m-d H:i:s'),
            'status'=>'active'
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_minutes'])) {
    header('Content-Type: application/json');
    $date = trim($_POST['meeting_date'] ?? '');
    $time = trim($_POST['meeting_time'] ?? '');
    $att = trim($_POST['attendees'] ?? '');
    $top = trim($_POST['topics'] ?? '');
    $dec = trim($_POST['decisions'] ?? '');
    if ($date === '' || $time === '') { echo json_encode(['ok'=>false,'error'=>'missing_datetime']); exit(); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS meeting_minutes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_date DATE,
            meeting_time TIME,
            attendees TEXT,
            topics TEXT,
            decisions TEXT,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmtI = $pdo->prepare("INSERT INTO meeting_minutes (meeting_date, meeting_time, attendees, topics, decisions) VALUES (?, ?, ?, ?, ?)");
        $stmtI->execute([$date, $time, $att, $top, $dec]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'meeting_date'=>$date,
            'meeting_time'=>$time,
            'attendees'=>$att,
            'topics'=>$top,
            'decisions'=>$dec
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_draft'])) {
    header('Content-Type: application/json');
    $docno = trim($_POST['doc_no'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['doc_type'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title === '') { echo json_encode(['ok'=>false,'error'=>'missing_title']); exit(); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS document_drafts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doc_no VARCHAR(100),
            title VARCHAR(255) NOT NULL,
            doc_type VARCHAR(50),
            subject VARCHAR(255),
            content TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('draft','finalized') DEFAULT 'draft'
        )");
        $stmtI = $pdo->prepare("INSERT INTO document_drafts (doc_no, title, doc_type, subject, content, status) VALUES (?, ?, ?, ?, ?, 'draft')");
        $stmtI->execute([$docno, $title, $type, $subject, $content]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'doc_no'=>$docno,
            'title'=>$title,
            'doc_type'=>$type,
            'subject'=>$subject,
            'created_at'=>date('Y-m-d H:i:s'),
            'status'=>'draft'
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_upload'])) {
    header('Content-Type: application/json');
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['doc_type'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    if ($title === '') { echo json_encode(['ok'=>false,'error'=>'missing_title']); exit(); }
    if (!isset($_FILES['archive_file']) || !is_uploaded_file($_FILES['archive_file']['tmp_name'])) {
        echo json_encode(['ok'=>false,'error'=>'missing_file']); exit();
    }
    $file = $_FILES['archive_file'];
    $baseDir = dirname(__DIR__);
    $uploadDir = $baseDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'archive';
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
    $orig = $file['name'] ?? ('file_'.time());
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
    $unique = time() . '_' . $safe;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $unique;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['ok'=>false,'error'=>'move_failed']); exit();
    }
    $relPath = 'uploads/archive/' . $unique;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS document_archive (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            doc_type VARCHAR(50),
            subject VARCHAR(255),
            file_name VARCHAR(255),
            file_path VARCHAR(255),
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('uploaded','finalized','metadata_only') DEFAULT 'uploaded'
        )");
        $stmtI = $pdo->prepare("INSERT INTO document_archive (title, doc_type, subject, file_name, file_path, status) VALUES (?, ?, ?, ?, ?, 'uploaded')");
        $stmtI->execute([$title, $type, $subject, $unique, $relPath]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'title'=>$title,
            'doc_type'=>$type,
            'subject'=>$subject,
            'file_name'=>$unique,
            'file_path'=>$relPath,
            'uploaded_at'=>date('Y-m-d H:i:s'),
            'status'=>'uploaded'
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_classification'])) {
    header('Content-Type: application/json');
    $y = intval($_POST['date_year'] ?? 0);
    $m = intval($_POST['date_month'] ?? 0);
    $d = intval($_POST['date_day'] ?? 0);
    $type = trim($_POST['doc_type'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $title = trim($_POST['document_title'] ?? '');
    if ($title === '') { echo json_encode(['ok'=>false,'error'=>'missing_title']); exit(); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS document_classifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date_year INT,
            date_month INT,
            date_day INT,
            doc_type VARCHAR(50),
            subject VARCHAR(255),
            document_title VARCHAR(255),
            tagged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmtI = $pdo->prepare("INSERT INTO document_classifications (date_year, date_month, date_day, doc_type, subject, document_title) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtI->execute([$y, $m, $d, $type, $subject, $title]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'date_year'=>$y,
            'date_month'=>$m,
            'date_day'=>$d,
            'doc_type'=>$type,
            'subject'=>$subject,
            'document_title'=>$title
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_incoming'])) {
    header('Content-Type: application/json');
    $ref = trim($_POST['reference_no'] ?? '');
    $sender = trim($_POST['sender_name'] ?? '');
    $stype = trim($_POST['sender_type'] ?? '');
    $via = trim($_POST['received_via'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $date = trim($_POST['date_received'] ?? '');
    if ($sender === '' || $subject === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit(); }
    if ($date === '') { $date = date('Y-m-d'); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS incoming_communications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference_no VARCHAR(100),
            sender_name VARCHAR(255) NOT NULL,
            sender_type VARCHAR(50),
            received_via VARCHAR(50),
            subject VARCHAR(255),
            summary TEXT,
            date_received DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmtI = $pdo->prepare("INSERT INTO incoming_communications (reference_no, sender_name, sender_type, received_via, subject, summary, date_received) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtI->execute([$ref, $sender, $stype, $via, $subject, $summary, $date]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'reference_no'=>$ref,
            'sender_name'=>$sender,
            'sender_type'=>$stype,
            'received_via'=>$via,
            'subject'=>$subject,
            'summary'=>$summary,
            'date_received'=>$date
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_outgoing'])) {
    header('Content-Type: application/json');
    $ref = trim($_POST['reference_no'] ?? '');
    $rtype = trim($_POST['doc_type'] ?? '');
    $recipient = trim($_POST['recipient_name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $date = trim($_POST['date_sent'] ?? '');
    $sentBy = trim($_POST['sent_by'] ?? '');
    if ($recipient === '' || $subject === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit(); }
    if ($date === '') { $date = date('Y-m-d'); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS outgoing_communications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference_no VARCHAR(100),
            doc_type VARCHAR(100),
            recipient_name VARCHAR(255) NOT NULL,
            subject VARCHAR(255),
            summary TEXT,
            date_sent DATE,
            sent_by VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmtI = $pdo->prepare("INSERT INTO outgoing_communications (reference_no, doc_type, recipient_name, subject, summary, date_sent, sent_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtI->execute([$ref, $rtype, $recipient, $subject, $summary, $date, $sentBy]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'reference_no'=>$ref,
            'doc_type'=>$rtype,
            'recipient_name'=>$recipient,
            'subject'=>$subject,
            'summary'=>$summary,
            'date_sent'=>$date,
            'sent_by'=>$sentBy
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_ack'])) {
    header('Content-Type: application/json');
    $direction = trim($_POST['direction'] ?? '');
    $ref = trim($_POST['reference_no'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $party = trim($_POST['counterpart_name'] ?? '');
    $date = trim($_POST['date_sent_received'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $ackDate = trim($_POST['ack_date'] ?? '');
    $ackBy = trim($_POST['ack_by'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if ($direction === '' || $party === '') { echo json_encode(['ok'=>false,'error'=>'missing_fields']); exit(); }
    if ($date === '') { $date = date('Y-m-d'); }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS communication_acknowledgments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            direction ENUM('incoming','outgoing') NOT NULL,
            reference_no VARCHAR(100),
            subject VARCHAR(255),
            counterpart_name VARCHAR(255),
            date_sent_received DATE,
            status ENUM('acknowledged','received','pending') DEFAULT 'pending',
            ack_date DATE NULL,
            ack_by VARCHAR(255),
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $stmtI = $pdo->prepare("INSERT INTO communication_acknowledgments (direction, reference_no, subject, counterpart_name, date_sent_received, status, ack_date, ack_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtI->execute([$direction, $ref, $subject, $party, $date, ($status!==''?$status:'pending'), ($ackDate!==''?$ackDate:null), $ackBy, $notes]);
        $stmtI = null;
        echo json_encode(['ok'=>true,'record'=>[
            'direction'=>$direction,
            'reference_no'=>$ref,
            'subject'=>$subject,
            'counterpart_name'=>$party,
            'date_sent_received'=>$date,
            'status'=>($status!==''?$status:'pending'),
            'ack_date'=>$ackDate,
            'ack_by'=>$ackBy,
            'notes'=>$notes
        ]]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}

session_start();
require_once '../config/db_connection.php';

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


if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$__sec_action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $____ = ($__sec_action === 'role_messages_list')) {
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
        $stmt = $pdo->prepare("SELECT enc_message, iv, message, created_at FROM messages WHERE recipient_role = 'SECRETARY' ORDER BY created_at DESC, id DESC LIMIT 200");
        $stmt->execute([]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $t = cps_decrypt_text($r['enc_message'] ?? '', $r['iv'] ?? '');
            if ($t === '' && !empty($r['message'])) { $t = (string)$r['message']; }
            $out[] = ['message' => $t, 'created_at' => $r['created_at']];
        }
        echo json_encode(['success'=>true,'messages'=>$out]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'messages'=>[]]);
        exit();
    }
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
    <title>Community Policing & Surveillance Management</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .settings-dropdown-container { position: relative; }
        .settings-dropdown-menu { position: absolute; right: 0; top: calc(100% + 8px); min-width: 160px; background: rgba(255,255,255,0.6); border: 1px solid #e5e7eb; box-shadow: 0 10px 25px rgba(0,0,0,0.08); border-radius: 8px; display: none; padding: 8px; z-index: 1000; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
        .settings-dropdown-menu.active { display: block; }
        .settings-dropdown-item { width: 100%; padding: 10px 12px; border: none; background: rgba(255,255,255,0.1); color: #111827; text-align: left; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; }
        .settings-dropdown-item:hover { background-color: rgba(255,255,255,0.2); }
        .settings-card { padding: 24px; border-radius: 16px; background: #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .settings-nav { display:flex; gap:12px; border-bottom:1px solid #e5e7eb; margin-bottom:16px; }
        .settings-tab { padding:8px 16px; border-radius:8px; cursor:pointer; border:1px solid #e5e7eb; background: rgba(255,255,255,0.6); color: #111827; }
        .settings-tab.active { background: #e9f7ef; color: #15803d; border-color: transparent; }
        .settings-title { font-size:20px; font-weight:600; margin-bottom: 12px; }
        .badge { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; }
        .badge-active { background:#e6f9ed; color:#15803d; }
        .badge-pending { background:#fff7ed; color:#c2410c; }
        .modal-input { font-size:16px; padding:12px; border:1px solid #e5e7eb; border-radius:8px; }
    </style>
</head>
<body>
    <div class="dashboard-animation" id="dashboard-animation">
        <div class="animation-logo">
            <div class="animation-logo-icon">
                <img src="../img/cpas-logo.png" alt="Community Policing & Surveillance Logo" style="width: 70px; height: 75px;">
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
                    <img src="../img/cpas-logo.png" alt="Community Policing & Surveillance Logo" style="width: 40px; height: 45px;">
                </div>
                <span class="logo-text">Community Policing & Surveillance</span>
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
                        <span class="font-medium">Offical Resolution Records</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="fire-incident" class="submenu">
                        <a href="#" class="submenu-item" id="barangay-resolution-records-link">Barangay Resolution Records</a>
                        <a href="#" class="submenu-item" id="ordinance-records-link">Ordinance Records</a>
                        <a href="#" class="submenu-item" id="exec-orders-memo-link">Executive Orders and Memo</a>
                    </div>
                    
                    <!-- Dispatch Coordination -->
                    <div class="menu-item" onclick="toggleSubmenu('dispatch')">
                        <div class="icon-box icon-bg-yellow">
                            <i class='bx bxs-truck icon-yellow'></i>
                        </div>
                        <span class="font-medium">Document Creation & Archiving</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="dispatch" class="submenu">
                        <a href="#" class="submenu-item" id="document-drafting-link">Document Drafting Tool</a>
                        <a href="#" class="submenu-item" id="digital-archiving-link">Digital Archiving System</a>
                        <a href="#" class="submenu-item" id="document-classification-link">Document Classification & Tagging</a>
                    </div>
                    
                    <!-- Barangay Volunteer Roster Access -->
                    <div class="menu-item" onclick="toggleSubmenu('volunteer')">
                        <div class="icon-box icon-bg-blue">
                            <i class='bx bxs-user-detail icon-blue'></i>
                        </div>
                        <span class="font-medium">Certification & Clearance Records</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="volunteer" class="submenu">
                        <a href="#" class="submenu-item" id="barangay-clearance-link">Barangay Clearance Records</a>
                        <a href="#" class="submenu-item" id="residency-certificate-link">Residency Certificate Records</a>
                        <a href="#" class="submenu-item" id="issued-document-logs-link">Issued Document Logs</a>
                    </div>
                    
                    <!-- Resource Inventory Updates -->
                    <div class="menu-item" onclick="toggleSubmenu('inventory')">
                        <div class="icon-box icon-bg-green">
                            <i class='bx bxs-cube icon-green'></i>
                        </div>
                        <span class="font-medium">Communication Logs</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="inventory" class="submenu">
                        <a href="#" class="submenu-item" id="incoming-comms-link">Incoming Communications Log</a>
                        <a href="#" class="submenu-item" id="outgoing-letters-link">Outgoing Letters and Notices</a>
                        <a href="#" class="submenu-item" id="ack-receipt-link">Acknowledgment and Receipt Tracking</a>
                    </div>
                    
                    <!-- Shift & Duty Scheduling -->
                    <div class="menu-item" onclick="toggleSubmenu('schedule')">
                        <div class="icon-box icon-bg-purple">
                            <i class='bx bxs-calendar icon-purple'></i>
                        </div>
                        <span class="font-medium">Approval & Signatory</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="schedule" class="submenu">
                        <a href="#" class="submenu-item" id="document-routing-link">Document Routing Approval</a>
                        <a href="#" class="submenu-item" id="signature-tracking-link">Digital Signature Tracking</a>
                        <a href="#" class="submenu-item" id="approval-monitoring-link">Approval Status Monitoring</a>
                    </div>
                    
                    <!-- Training & Certification Logging -->
                    <div class="menu-item" onclick="toggleSubmenu('training')">
                        <div class="icon-box icon-bg-teal">
                            <i class='bx bxs-graduation icon-teal'></i>
                        </div>
                        <span class="font-medium">Reports & Records Audit</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="training" class="submenu">
                        <a href="#" class="submenu-item" id="monthly-summary-link">Monthly Records Summary</a>
                        <a href="#" class="submenu-item" id="inventory-report-link">Document Inventory Report</a>
                        <a href="#" class="submenu-item" id="audit-trail-link">Audit Trail and Change Logs</a>
                        <a href="#" class="submenu-item" id="compliance-report-link">Compliance and Retention Reports</a>
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
                    <a href="#" class="menu-item" id="admin-messages-link">
                        <div class="icon-box icon-bg-indigo">
                            <i class='bx bxs-message-rounded icon-indigo'></i>
                        </div>
                        <span class="font-medium">Admin Messages</span>
                    </a>
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
            
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div id="settings-profile-section" style="display:none;">
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
                                <div id="avatar-status" style="font-weight:500;"></div>
                            </div>
                            <form id="profile-form" style="flex:1;min-width:320px;max-width:640px;">
                                <div style="display:grid;grid-template-columns:1fr;gap:12px;">
                                    <input class="modal-input" type="text" name="first_name" placeholder="First name">
                                    <input class="modal-input" type="text" name="middle_name" placeholder="Middle name">
                                    <input class="modal-input" type="text" name="last_name" placeholder="Last name">
                                    <input class="modal-input" type="text" name="username" placeholder="Username">
                                    <input class="modal-input" type="email" name="email" placeholder="Email">
                                    <input class="modal-input" type="text" name="contact" placeholder="Contact">
                                    <input class="modal-input" type="date" name="date_of_birth" placeholder="Date of birth">
                                    <input class="modal-input" type="text" name="address" placeholder="Address">
                                </div>
                                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                                    <button type="submit" class="primary-button" id="profile-save-btn">Save Changes</button>
                                </div>
                                <div id="profile-status" style="margin-top:8px;font-weight:500;"></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div id="admin-messages-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Messages</h1>
                            <p class="dashboard-subtitle">Encrypted messages from Admin</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="admin-messages-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid" style="grid-template-columns: 1fr;">
                        <div class="left-column">
                            <div class="card" style="height:100%;">
                                <h2 class="card-title">Inbox</h2>
                                <div id="role-msg-chat" style="padding:12px;max-height:420px;overflow-y:auto;background:#f9fafb;border-radius:8px;margin:12px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="settings-security-section" style="display:none;">
                    <div class="settings-card">
                        <div class="settings-nav">
                            <span class="settings-tab">Profile</span>
                            <span class="settings-tab active">Security</span>
                        </div>
                        <div class="settings-title">Security</div>
                        <div class="settings-list">
                            <div class="settings-item" style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #e5e7eb;">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div class="settings-item-icon"><i class='bx bxs-lock-alt'></i></div>
                                    <div>
                                        <div class="settings-item-title">Change Password</div>
                                        <div class="settings-item-desc" id="pwd-last-changed">Last changed 3 months ago</div>
                                    </div>
                                </div>
                                <button class="secondary-button" id="security-change-password-btn">Change</button>
                            </div>
                            <div class="settings-item" style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #e5e7eb;">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div class="settings-item-icon"><i class='bx bxs-envelope'></i></div>
                                    <div>
                                        <div class="settings-item-title">Email Address</div>
                                        <div class="settings-item-desc" id="email-address"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
                                    </div>
                                </div>
                                <button class="secondary-button" id="security-change-email-btn">Change</button>
                            </div>
                            <div class="settings-item" style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #e5e7eb;">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div class="settings-item-icon"><i class='bx bxs-key'></i></div>
                                    <div>
                                        <div class="settings-item-title">API Access</div>
                                        <div class="settings-item-desc" id="api-status">No API key generated</div>
                                    </div>
                                </div>
                                <button class="secondary-button" id="security-generate-key-btn">+ Generate Key</button>
                            </div>
                            <div class="settings-item" style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <div class="settings-item-icon"><i class='bx bxs-shield'></i></div>
                                    <div>
                                        <div class="settings-item-title">Two-Factor Authentication</div>
                                        <div class="settings-item-desc" id="tfa-status"><span class="badge badge-pending">Disabled</span></div>
                                    </div>
                                </div>
                                <button class="secondary-button" id="security-enable-2fa-btn">Enable</button>
                            </div>
                        </div>
                        <div class="settings-danger" style="margin-top:16px;">
                            <div class="settings-danger-title"><i class='bx bxs-error'></i> Danger Zone</div>
                            <div class="settings-item-desc">Once you delete your account, there is no going back. Please be certain.</div>
                            <div style="margin-top:8px;">
                                <button class="secondary-button" id="security-delete-account-btn" style="background:#ef4444;color:#fff;border-color:#ef4444;">Delete Account</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="document-drafting-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Document Drafting Tool</h1>
                            <p class="dashboard-subtitle">Create official barangay documents directly in the system.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="drafting-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Drafts</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Doc No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Title</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Subject</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Created At</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="document-drafts-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS document_drafts (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        doc_no VARCHAR(100),
                                                        title VARCHAR(255) NOT NULL,
                                                        doc_type VARCHAR(50),
                                                        subject VARCHAR(255),
                                                        content TEXT,
                                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                        status ENUM('draft','finalized') DEFAULT 'draft'
                                                    )");
                                                    $stmtDD = $pdo->prepare("SELECT doc_no, title, doc_type, subject, created_at, status FROM document_drafts ORDER BY created_at DESC");
                                                    $stmtDD->execute([]);
                                                    $draftRows = $stmtDD->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtDD = null;
                                                } catch (Exception $e) {
                                                    $draftRows = [];
                                                }
                                                if (!empty($draftRows)) {
                                                    foreach ($draftRows as $d) {
                                                        $no = htmlspecialchars($d['doc_no'] ?? '');
                                                        if ($no === '') { $no = '—'; }
                                                        $title = htmlspecialchars($d['title'] ?? '');
                                                        if ($title === '') { $title = '—'; }
                                                        $type = htmlspecialchars($d['doc_type'] ?? '');
                                                        if ($type === '') { $type = '—'; }
                                                        $subj = htmlspecialchars($d['subject'] ?? '');
                                                        if ($subj === '') { $subj = '—'; }
                                                        $dt = htmlspecialchars(substr((string)($d['created_at'] ?? ''), 0, 19));
                                                        $st = htmlspecialchars($d['status'] ?? 'draft');
                                                        $label = ucfirst($st);
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $no . '</td>';
                                                        echo '<td style="padding:10px;">' . $title . '</td>';
                                                        echo '<td style="padding:10px;">' . $type . '</td>';
                                                        echo '<td style="padding:10px;">' . $subj . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $label . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" style="padding:14px;">No drafts found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Create Document</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Document No.</label>
                                        <input id="draft-docno-input" type="text" placeholder="e.g., DOC-2026-010" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Title</label>
                                        <input id="draft-title-input" type="text" placeholder="Title" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Type</label>
                                        <select id="draft-type-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Resolution">Resolution</option>
                                            <option value="Certificate">Certificate</option>
                                            <option value="Official Letter">Official Letter</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Subject</label>
                                        <input id="draft-subject-input" type="text" placeholder="e.g., Peace and Order" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Content</label>
                                        <textarea id="draft-content-input" rows="6" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="draft-submit-btn">Save Draft</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="digital-archiving-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Digital Archiving System</h1>
                            <p class="dashboard-subtitle">Secure storage for scanned and finalized documents.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="archiving-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Archive</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Title</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Subject</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">File</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Uploaded At</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="archive-records-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS document_archive (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        title VARCHAR(255) NOT NULL,
                                                        doc_type VARCHAR(50),
                                                        subject VARCHAR(255),
                                                        file_name VARCHAR(255),
                                                        file_path VARCHAR(255),
                                                        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                        status ENUM('uploaded','finalized','metadata_only') DEFAULT 'uploaded'
                                                    )");
                                                    $stmtAR = $pdo->prepare("SELECT title, doc_type, subject, file_name, file_path, uploaded_at, status FROM document_archive ORDER BY uploaded_at DESC");
                                                    $stmtAR->execute([]);
                                                    $arcRows = $stmtAR->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtAR = null;
                                                } catch (Exception $e) {
                                                    $arcRows = [];
                                                }
                                                if (!empty($arcRows)) {
                                                    foreach ($arcRows as $a) {
                                                        $title = htmlspecialchars($a['title'] ?? '');
                                                        if ($title === '') { $title = '—'; }
                                                        $type = htmlspecialchars($a['doc_type'] ?? '');
                                                        if ($type === '') { $type = '—'; }
                                                        $subj = htmlspecialchars($a['subject'] ?? '');
                                                        if ($subj === '') { $subj = '—'; }
                                                        $fname = htmlspecialchars($a['file_name'] ?? '');
                                                        if ($fname === '') { $fname = '—'; }
                                                        $dt = htmlspecialchars(substr((string)($a['uploaded_at'] ?? ''), 0, 19));
                                                        $st = htmlspecialchars($a['status'] ?? 'uploaded');
                                                        $label = ucfirst($st);
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $title . '</td>';
                                                        echo '<td style="padding:10px;">' . $type . '</td>';
                                                        echo '<td style="padding:10px;">' . $subj . '</td>';
                                                        echo '<td style="padding:10px;">' . $fname . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $label . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" style="padding:14px;">No archived documents found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Upload Document</h2>
                                <form id="archive-upload-form" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Title</label>
                                        <input id="archive-title-input" name="title" type="text" placeholder="Title" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Type</label>
                                        <select id="archive-type-select" name="doc_type" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Resolution">Resolution</option>
                                            <option value="Ordinance">Ordinance</option>
                                            <option value="Certificate">Certificate</option>
                                            <option value="Official Letter">Official Letter</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Subject</label>
                                        <input id="archive-subject-input" name="subject" type="text" placeholder="Subject" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">File (PDF/Image)</label>
                                        <input id="archive-file-input" name="archive_file" type="file" accept=".pdf,image/*" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button type="submit" class="primary-button" id="archive-upload-btn">Upload</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="document-classification-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Document Classification & Tagging</h1>
                            <p class="dashboard-subtitle">Organize documents by date, type, and subject.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="classification-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Classifications</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Year</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Month</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Day</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Subject</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Document Title</th>
                                            </tr>
                                        </thead>
                                        <tbody id="classification-records-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS document_classifications (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        date_year INT,
                                                        date_month INT,
                                                        date_day INT,
                                                        doc_type VARCHAR(50),
                                                        subject VARCHAR(255),
                                                        document_title VARCHAR(255),
                                                        tagged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                                    )");
                                                    $stmtCL = $pdo->prepare("SELECT date_year, date_month, date_day, doc_type, subject, document_title FROM document_classifications ORDER BY tagged_at DESC");
                                                    $stmtCL->execute([]);
                                                    $clsRows = $stmtCL->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtCL = null;
                                                } catch (Exception $e) {
                                                    $clsRows = [];
                                                }
                                                if (!empty($clsRows)) {
                                                    foreach ($clsRows as $c) {
                                                        $y = htmlspecialchars((string)($c['date_year'] ?? ''));
                                                        if ($y === '') { $y = '—'; }
                                                        $m = htmlspecialchars((string)($c['date_month'] ?? ''));
                                                        if ($m === '') { $m = '—'; }
                                                        $d = htmlspecialchars((string)($c['date_day'] ?? ''));
                                                        if ($d === '') { $d = '—'; }
                                                        $type = htmlspecialchars($c['doc_type'] ?? '');
                                                        if ($type === '') { $type = '—'; }
                                                        $subj = htmlspecialchars($c['subject'] ?? '');
                                                        if ($subj === '') { $subj = '—'; }
                                                        $title = htmlspecialchars($c['document_title'] ?? '');
                                                        if ($title === '') { $title = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $y . '</td>';
                                                        echo '<td style="padding:10px;">' . $m . '</td>';
                                                        echo '<td style="padding:10px;">' . $d . '</td>';
                                                        echo '<td style="padding:10px;">' . $type . '</td>';
                                                        echo '<td style="padding:10px;">' . $subj . '</td>';
                                                        echo '<td style="padding:10px;">' . $title . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" style="padding:14px;">No classifications found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Add Classification</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div style="display:flex;gap:10px;">
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Year</label>
                                            <input id="class-year-input" type="number" placeholder="2026" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Month</label>
                                            <input id="class-month-input" type="number" placeholder="1-12" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Day</label>
                                            <input id="class-day-input" type="number" placeholder="1-31" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Type</label>
                                        <select id="class-type-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Resolution">Resolution</option>
                                            <option value="Ordinance">Ordinance</option>
                                            <option value="Certificate">Certificate</option>
                                            <option value="Official Letter">Official Letter</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Subject</label>
                                        <input id="class-subject-input" type="text" placeholder="e.g., Health" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Document Title</label>
                                        <input id="class-title-input" type="text" placeholder="Document title" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="class-submit-btn">Add</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="barangay-clearance-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Barangay Clearance Records</h1>
                            <p class="dashboard-subtitle">Issued clearances to residents with no pending complaints.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="clearance-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Clearances</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Clearance No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Resident</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Address</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Verified</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Issued At</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Issued By</th>
                                            </tr>
                                        </thead>
                                        <tbody id="clearance-records-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_clearances (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        clearance_no VARCHAR(100),
                                                        resident_name VARCHAR(255) NOT NULL,
                                                        resident_address VARCHAR(255),
                                                        verified_no_issues ENUM('yes','no') DEFAULT 'yes',
                                                        issued_by VARCHAR(255),
                                                        issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                                    )");
                                                    $stmtBC = $pdo->prepare("SELECT clearance_no, resident_name, resident_address, verified_no_issues, issued_at, issued_by FROM barangay_clearances ORDER BY issued_at DESC");
                                                    $stmtBC->execute([]);
                                                    $clrRows = $stmtBC->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtBC = null;
                                                } catch (Exception $e) {
                                                    $clrRows = [];
                                                }
                                                if (!empty($clrRows)) {
                                                    foreach ($clrRows as $c) {
                                                        $no = htmlspecialchars($c['clearance_no'] ?? '');
                                                        if ($no === '') { $no = '—'; }
                                                        $name = htmlspecialchars($c['resident_name'] ?? '');
                                                        if ($name === '') { $name = '—'; }
                                                        $addr = htmlspecialchars($c['resident_address'] ?? '');
                                                        if ($addr === '') { $addr = '—'; }
                                                        $ver = htmlspecialchars($c['verified_no_issues'] ?? 'yes');
                                                        $verLabel = ($ver==='no'?'No':'Yes');
                                                        $dt = htmlspecialchars(substr((string)($c['issued_at'] ?? ''), 0, 19));
                                                        $by = htmlspecialchars($c['issued_by'] ?? '');
                                                        if ($by === '') { $by = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $no . '</td>';
                                                        echo '<td style="padding:10px;">' . $name . '</td>';
                                                        echo '<td style="padding:10px;">' . $addr . '</td>';
                                                        echo '<td style="padding:10px;">' . $verLabel . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $by . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" style="padding:14px;">No clearances found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Issue Clearance</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Clearance No.</label>
                                        <input id="clearance-no-input" type="text" placeholder="e.g., BC-2026-001" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Resident Name</label>
                                        <input id="clearance-name-input" type="text" placeholder="Full name" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Address</label>
                                        <input id="clearance-address-input" type="text" placeholder="Address" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Verified No Issues</label>
                                        <select id="clearance-verified-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="yes">Yes</option>
                                            <option value="no">No</option>
                                        </select>
                                    </div>
                                    <div style="display:flex;gap:10px;">
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Date Issued</label>
                                            <input id="clearance-date-input" type="date" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Issued By</label>
                                            <input id="clearance-issuedby-input" type="text" placeholder="Secretary name" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="clearance-submit-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="residency-certificate-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Residency Certificate Records</h1>
                            <p class="dashboard-subtitle">Certificates confirming residency and period of stay.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="residency-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Residency Certificates</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Certificate No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Resident</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Address</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Period</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Issued At</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Issued By</th>
                                            </tr>
                                        </thead>
                                        <tbody id="residency-records-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS residency_certificates (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        certificate_no VARCHAR(100),
                                                        resident_name VARCHAR(255) NOT NULL,
                                                        resident_address VARCHAR(255),
                                                        start_date DATE,
                                                        end_date DATE,
                                                        issued_by VARCHAR(255),
                                                        issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                                    )");
                                                    $stmtRC = $pdo->prepare("SELECT certificate_no, resident_name, resident_address, start_date, end_date, issued_at, issued_by FROM residency_certificates ORDER BY issued_at DESC");
                                                    $stmtRC->execute([]);
                                                    $resRows2 = $stmtRC->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtRC = null;
                                                } catch (Exception $e) {
                                                    $resRows2 = [];
                                                }
                                                if (!empty($resRows2)) {
                                                    foreach ($resRows2 as $r2) {
                                                        $no = htmlspecialchars($r2['certificate_no'] ?? '');
                                                        if ($no === '') { $no = '—'; }
                                                        $name = htmlspecialchars($r2['resident_name'] ?? '');
                                                        if ($name === '') { $name = '—'; }
                                                        $addr = htmlspecialchars($r2['resident_address'] ?? '');
                                                        if ($addr === '') { $addr = '—'; }
                                                        $sd = htmlspecialchars($r2['start_date'] ?? '');
                                                        $ed = htmlspecialchars($r2['end_date'] ?? '');
                                                        $period = ($sd!=='' && $ed!=='' ? ($sd . ' to ' . $ed) : '—');
                                                        $dt = htmlspecialchars(substr((string)($r2['issued_at'] ?? ''), 0, 19));
                                                        $by = htmlspecialchars($r2['issued_by'] ?? '');
                                                        if ($by === '') { $by = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $no . '</td>';
                                                        echo '<td style="padding:10px;">' . $name . '</td>';
                                                        echo '<td style="padding:10px;">' . $addr . '</td>';
                                                        echo '<td style="padding:10px;">' . $period . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $by . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" style="padding:14px;">No residency certificates found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Issue Residency Certificate</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Certificate No.</label>
                                        <input id="residency-no-input" type="text" placeholder="e.g., RC-2026-001" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Resident Name</label>
                                        <input id="residency-name-input" type="text" placeholder="Full name" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Address</label>
                                        <input id="residency-address-input" type="text" placeholder="Address" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div style="display:flex;gap:10px;">
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Start Date</label>
                                            <input id="residency-start-input" type="date" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">End Date</label>
                                            <input id="residency-end-input" type="date" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:10px;">
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Date Issued</label>
                                            <input id="residency-date-input" type="date" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Issued By</label>
                                            <input id="residency-issuedby-input" type="text" placeholder="Secretary name" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="residency-submit-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="issued-document-logs-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Issued Document Logs</h1>
                            <p class="dashboard-subtitle">Master log of all issued documents.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="logs-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Logs</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Doc No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Recipient</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date Issued</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Issued By</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Logged At</th>
                                            </tr>
                                        </thead>
                                        <tbody id="issued-logs-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS issued_document_logs (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        doc_type VARCHAR(100),
                                                        doc_no VARCHAR(100),
                                                        recipient_name VARCHAR(255),
                                                        date_issued DATE,
                                                        issued_by VARCHAR(255),
                                                        logged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                        UNIQUE KEY uniq_doc (doc_type, doc_no)
                                                    )");
                                                    $stmtIL = $pdo->prepare("SELECT doc_type, doc_no, recipient_name, date_issued, issued_by, logged_at FROM issued_document_logs ORDER BY logged_at DESC");
                                                    $stmtIL->execute([]);
                                                    $logRows = $stmtIL->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtIL = null;
                                                } catch (Exception $e) {
                                                    $logRows = [];
                                                }
                                                if (!empty($logRows)) {
                                                    foreach ($logRows as $l) {
                                                        $type = htmlspecialchars($l['doc_type'] ?? '');
                                                        if ($type === '') { $type = '—'; }
                                                        $no = htmlspecialchars($l['doc_no'] ?? '');
                                                        if ($no === '') { $no = '—'; }
                                                        $rec = htmlspecialchars($l['recipient_name'] ?? '');
                                                        if ($rec === '') { $rec = '—'; }
                                                        $di = htmlspecialchars($l['date_issued'] ?? '');
                                                        if ($di === '') { $di = '—'; }
                                                        $by = htmlspecialchars($l['issued_by'] ?? '');
                                                        if ($by === '') { $by = '—'; }
                                                        $dt = htmlspecialchars(substr((string)($l['logged_at'] ?? ''), 0, 19));
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $type . '</td>';
                                                        echo '<td style="padding:10px;">' . $no . '</td>';
                                                        echo '<td style="padding:10px;">' . $rec . '</td>';
                                                        echo '<td style="padding:10px;">' . $di . '</td>';
                                                        echo '<td style="padding:10px;">' . $by . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" style="padding:14px;">No logs found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Add Log Entry</h2>
                                <form id="issued-log-form" style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Document Type</label>
                                        <select id="issued-type-select" name="doc_type" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Barangay Clearance">Barangay Clearance</option>
                                            <option value="Residency Certificate">Residency Certificate</option>
                                            <option value="Resolution">Resolution</option>
                                            <option value="Ordinance">Ordinance</option>
                                            <option value="Certificate">Certificate</option>
                                            <option value="Official Letter">Official Letter</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Doc No.</label>
                                        <input id="issued-docno-input" name="doc_no" type="text" placeholder="e.g., BC-2026-001" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Recipient</label>
                                        <input id="issued-recipient-input" name="recipient_name" type="text" placeholder="Full name" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div style="display:flex;gap:10px;">
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Date Issued</label>
                                            <input id="issued-date-input" name="date_issued" type="date" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Issued By</label>
                                            <input id="issued-by-input" name="issued_by" type="text" placeholder="Secretary name" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button type="submit" class="primary-button" id="issued-log-submit-btn">Add</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="incoming-comms-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Incoming Communications Log</h1>
                            <p class="dashboard-subtitle">Records of letters, complaints, notices, and emails received.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="incoming-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Incoming</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Ref No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Sender</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Via</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Subject</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Summary</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date Received</th>
                                            </tr>
                                        </thead>
                                        <tbody id="incoming-comms-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS incoming_communications (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        reference_no VARCHAR(100),
                                                        sender_name VARCHAR(255) NOT NULL,
                                                        sender_type VARCHAR(50),
                                                        received_via VARCHAR(50),
                                                        subject VARCHAR(255),
                                                        summary TEXT,
                                                        date_received DATE,
                                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                                    )");
                                                    $stmtIN = $pdo->prepare("SELECT reference_no, sender_name, sender_type, received_via, subject, summary, date_received FROM incoming_communications ORDER BY created_at DESC");
                                                    $stmtIN->execute([]);
                                                    $inRows = $stmtIN->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtIN = null;
                                                } catch (Exception $e) {
                                                    $inRows = [];
                                                }
                                                if (!empty($inRows)) {
                                                    foreach ($inRows as $i) {
                                                        $ref = htmlspecialchars($i['reference_no'] ?? '');
                                                        if ($ref === '') { $ref = '—'; }
                                                        $sender = htmlspecialchars($i['sender_name'] ?? '');
                                                        if ($sender === '') { $sender = '—'; }
                                                        $stype = htmlspecialchars($i['sender_type'] ?? '');
                                                        if ($stype === '') { $stype = '—'; }
                                                        $via = htmlspecialchars($i['received_via'] ?? '');
                                                        if ($via === '') { $via = '—'; }
                                                        $subj = htmlspecialchars($i['subject'] ?? '');
                                                        if ($subj === '') { $subj = '—'; }
                                                        $sum = htmlspecialchars($i['summary'] ?? '');
                                                        if ($sum === '') { $sum = '—'; }
                                                        $date = htmlspecialchars($i['date_received'] ?? '');
                                                        if ($date === '') { $date = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $ref . '</td>';
                                                        echo '<td style="padding:10px;">' . $sender . '</td>';
                                                        echo '<td style="padding:10px;">' . $stype . '</td>';
                                                        echo '<td style="padding:10px;">' . $via . '</td>';
                                                        echo '<td style="padding:10px;">' . $subj . '</td>';
                                                        echo '<td style="padding:10px;">' . $sum . '</td>';
                                                        echo '<td style="padding:10px;">' . $date . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="7" style="padding:14px;">No incoming communications found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Record Incoming</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Reference No.</label>
                                        <input id="incoming-ref-input" type="text" placeholder="e.g., IN-2026-001" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Sender Name</label>
                                        <input id="incoming-sender-input" type="text" placeholder="Name" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Sender Type</label>
                                        <select id="incoming-type-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Resident">Resident</option>
                                            <option value="Agency">Agency</option>
                                            <option value="Organization">Organization</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Received Via</label>
                                        <select id="incoming-via-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Letter">Letter</option>
                                            <option value="Email">Email</option>
                                            <option value="Notice">Notice</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Subject</label>
                                        <input id="incoming-subject-input" type="text" placeholder="Subject" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Summary</label>
                                        <textarea id="incoming-summary-input" rows="4" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Date Received</label>
                                        <input id="incoming-date-input" type="date" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="incoming-submit-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="outgoing-letters-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Outgoing Letters and Notices</h1>
                            <p class="dashboard-subtitle">Records of official communications sent by the barangay.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="outgoing-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Outgoing</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Ref No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Recipient</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Subject</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Summary</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date Sent</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Sent By</th>
                                            </tr>
                                        </thead>
                                        <tbody id="outgoing-letters-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS outgoing_communications (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        reference_no VARCHAR(100),
                                                        doc_type VARCHAR(100),
                                                        recipient_name VARCHAR(255) NOT NULL,
                                                        subject VARCHAR(255),
                                                        summary TEXT,
                                                        date_sent DATE,
                                                        sent_by VARCHAR(255),
                                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                                    )");
                                                    $stmtOUT = $pdo->prepare("SELECT reference_no, doc_type, recipient_name, subject, summary, date_sent, sent_by FROM outgoing_communications ORDER BY created_at DESC");
                                                    $stmtOUT->execute([]);
                                                    $outRows = $stmtOUT->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtOUT = null;
                                                } catch (Exception $e) {
                                                    $outRows = [];
                                                }
                                                if (!empty($outRows)) {
                                                    foreach ($outRows as $o) {
                                                        $ref = htmlspecialchars($o['reference_no'] ?? '');
                                                        if ($ref === '') { $ref = '—'; }
                                                        $type = htmlspecialchars($o['doc_type'] ?? '');
                                                        if ($type === '') { $type = '—'; }
                                                        $rec = htmlspecialchars($o['recipient_name'] ?? '');
                                                        if ($rec === '') { $rec = '—'; }
                                                        $subj = htmlspecialchars($o['subject'] ?? '');
                                                        if ($subj === '') { $subj = '—'; }
                                                        $sum = htmlspecialchars($o['summary'] ?? '');
                                                        if ($sum === '') { $sum = '—'; }
                                                        $date = htmlspecialchars($o['date_sent'] ?? '');
                                                        if ($date === '') { $date = '—'; }
                                                        $by = htmlspecialchars($o['sent_by'] ?? '');
                                                        if ($by === '') { $by = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $ref . '</td>';
                                                        echo '<td style="padding:10px;">' . $type . '</td>';
                                                        echo '<td style="padding:10px;">' . $rec . '</td>';
                                                        echo '<td style="padding:10px;">' . $subj . '</td>';
                                                        echo '<td style="padding:10px;">' . $sum . '</td>';
                                                        echo '<td style="padding:10px;">' . $date . '</td>';
                                                        echo '<td style="padding:10px;">' . $by . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="7" style="padding:14px;">No outgoing communications found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Record Outgoing</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Reference No.</label>
                                        <input id="outgoing-ref-input" type="text" placeholder="e.g., OUT-2026-001" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Type</label>
                                        <select id="outgoing-type-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Invitation Letter">Invitation Letter</option>
                                            <option value="Notice">Notice</option>
                                            <option value="Endorsement">Endorsement</option>
                                            <option value="Official Response">Official Response</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Recipient</label>
                                        <input id="outgoing-recipient-input" type="text" placeholder="Full name or office" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Subject</label>
                                        <input id="outgoing-subject-input" type="text" placeholder="Subject" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Summary</label>
                                        <textarea id="outgoing-summary-input" rows="4" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div style="display:flex;gap:10px;">
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Date Sent</label>
                                            <input id="outgoing-date-input" type="date" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Sent By</label>
                                            <input id="outgoing-sentby-input" type="text" placeholder="Secretary name" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="outgoing-submit-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="ack-receipt-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Acknowledgment & Receipt Tracking</h1>
                            <p class="dashboard-subtitle">Track whether communications were acknowledged or received.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="ack-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Tracking</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Direction</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Ref No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Subject</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Counterpart</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Ack Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Ack By</th>
                                            </tr>
                                        </thead>
                                        <tbody id="ack-receipt-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS communication_acknowledgments (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        direction ENUM('incoming','outgoing') NOT NULL,
                                                        reference_no VARCHAR(100),
                                                        subject VARCHAR(255),
                                                        counterpart_name VARCHAR(255),
                                                        date_sent_received DATE,
                                                        status ENUM('acknowledged','received','pending') DEFAULT 'pending',
                                                        ack_date DATE NULL,
                                                        ack_by VARCHAR(255),
                                                        notes TEXT,
                                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                                    )");
                                                    $stmtAK = $pdo->prepare("SELECT direction, reference_no, subject, counterpart_name, date_sent_received, status, ack_date, ack_by FROM communication_acknowledgments ORDER BY created_at DESC");
                                                    $stmtAK->execute([]);
                                                    $ackRows = $stmtAK->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtAK = null;
                                                } catch (Exception $e) {
                                                    $ackRows = [];
                                                }
                                                if (!empty($ackRows)) {
                                                    foreach ($ackRows as $a) {
                                                        $dir = htmlspecialchars($a['direction'] ?? '');
                                                        if ($dir === '') { $dir = '—'; }
                                                        $ref = htmlspecialchars($a['reference_no'] ?? '');
                                                        if ($ref === '') { $ref = '—'; }
                                                        $subj = htmlspecialchars($a['subject'] ?? '');
                                                        if ($subj === '') { $subj = '—'; }
                                                        $cp = htmlspecialchars($a['counterpart_name'] ?? '');
                                                        if ($cp === '') { $cp = '—'; }
                                                        $date = htmlspecialchars($a['date_sent_received'] ?? '');
                                                        if ($date === '') { $date = '—'; }
                                                        $st = htmlspecialchars($a['status'] ?? 'pending');
                                                        $stLabel = ucfirst($st);
                                                        $ad = htmlspecialchars($a['ack_date'] ?? '');
                                                        if ($ad === '') { $ad = '—'; }
                                                        $ab = htmlspecialchars($a['ack_by'] ?? '');
                                                        if ($ab === '') { $ab = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $dir . '</td>';
                                                        echo '<td style="padding:10px;">' . $ref . '</td>';
                                                        echo '<td style="padding:10px;">' . $subj . '</td>';
                                                        echo '<td style="padding:10px;">' . $cp . '</td>';
                                                        echo '<td style="padding:10px;">' . $date . '</td>';
                                                        echo '<td style="padding:10px;">' . $stLabel . '</td>';
                                                        echo '<td style="padding:10px;">' . $ad . '</td>';
                                                        echo '<td style="padding:10px;">' . $ab . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="8" style="padding:14px;">No tracking records found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Add Tracking Entry</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Direction</label>
                                        <select id="ack-direction-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="incoming">Incoming</option>
                                            <option value="outgoing">Outgoing</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Reference No.</label>
                                        <input id="ack-ref-input" type="text" placeholder="Ref No." style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Subject</label>
                                        <input id="ack-subject-input" type="text" placeholder="Subject" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Counterpart Name</label>
                                        <input id="ack-counterpart-input" type="text" placeholder="Recipient/Sender" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Date (Sent/Received)</label>
                                        <input id="ack-date-sr-input" type="date" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Status</label>
                                        <select id="ack-status-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="pending">Pending</option>
                                            <option value="acknowledged">Acknowledged</option>
                                            <option value="received">Received</option>
                                        </select>
                                    </div>
                                    <div style="display:flex;gap:10px;">
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Ack Date</label>
                                            <input id="ack-date-input" type="date" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Ack By</label>
                                            <input id="ack-by-input" type="text" placeholder="Name" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Notes</label>
                                        <textarea id="ack-notes-input" rows="3" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="ack-submit-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="routing-approval-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Document Routing Approval</h1>
                            <p class="dashboard-subtitle">Route documents to the Barangay Captain for review and approval.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="routing-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Routed Documents</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Doc No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Title</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Routed To</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Routed At</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="routing-approval-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS document_routing (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        doc_no VARCHAR(100),
                                                        title VARCHAR(255) NOT NULL,
                                                        doc_type VARCHAR(100),
                                                        routed_to VARCHAR(255) DEFAULT 'Barangay Captain',
                                                        routed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                        status ENUM('Pending','Approved','Returned','Rejected') DEFAULT 'Pending',
                                                        remarks TEXT
                                                    )");
                                                    $stmtRT = $pdo->prepare("SELECT doc_no, title, doc_type, routed_to, routed_at, status FROM document_routing ORDER BY routed_at DESC");
                                                    $stmtRT->execute([]);
                                                    $rtRows = $stmtRT->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtRT = null;
                                                } catch (Exception $e) {
                                                    $rtRows = [];
                                                }
                                                if (!empty($rtRows)) {
                                                    foreach ($rtRows as $r) {
                                                        $no = htmlspecialchars($r['doc_no'] ?? '');
                                                        if ($no === '') { $no = '—'; }
                                                        $title = htmlspecialchars($r['title'] ?? '');
                                                        if ($title === '') { $title = '—'; }
                                                        $type = htmlspecialchars($r['doc_type'] ?? '');
                                                        if ($type === '') { $type = '—'; }
                                                        $to = htmlspecialchars($r['routed_to'] ?? 'Barangay Captain');
                                                        $dt = htmlspecialchars(substr((string)($r['routed_at'] ?? ''), 0, 19));
                                                        $st = htmlspecialchars($r['status'] ?? 'Pending');
                                                        $label = ucfirst($st);
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $no . '</td>';
                                                        echo '<td style="padding:10px;">' . $title . '</td>';
                                                        echo '<td style="padding:10px;">' . $type . '</td>';
                                                        echo '<td style="padding:10px;">' . $to . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $label . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" style="padding:14px;">No routed documents found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Route Document</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Doc No.</label>
                                        <input id="route-docno-input" type="text" placeholder="e.g., DOC-2026-012" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Title</label>
                                        <input id="route-title-input" type="text" placeholder="Title" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Type</label>
                                        <select id="route-type-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Resolution">Resolution</option>
                                            <option value="Memorandum">Memorandum</option>
                                            <option value="Request">Request</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Remarks</label>
                                        <textarea id="route-remarks-input" rows="3" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="route-submit-btn">Route</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="signature-tracking-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Digital Signature Tracking</h1>
                            <p class="dashboard-subtitle">Track who signed, when, and which document version.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="signature-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Signatures</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Doc No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Signer</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Signed At</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Version</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Authenticity</th>
                                            </tr>
                                        </thead>
                                        <tbody id="signature-tracking-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS document_signatures (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        doc_no VARCHAR(100),
                                                        signer_name VARCHAR(255) NOT NULL,
                                                        signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                        doc_version VARCHAR(50),
                                                        auth_status ENUM('verified','unverified') DEFAULT 'verified'
                                                    )");
                                                    $stmtSG = $pdo->prepare("SELECT doc_no, signer_name, signed_at, doc_version, auth_status FROM document_signatures ORDER BY signed_at DESC");
                                                    $stmtSG->execute([]);
                                                    $sigRows = $stmtSG->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtSG = null;
                                                } catch (Exception $e) {
                                                    $sigRows = [];
                                                }
                                                if (!empty($sigRows)) {
                                                    foreach ($sigRows as $s) {
                                                        $no = htmlspecialchars($s['doc_no'] ?? '');
                                                        if ($no === '') { $no = '—'; }
                                                        $signer = htmlspecialchars($s['signer_name'] ?? '');
                                                        if ($signer === '') { $signer = '—'; }
                                                        $dt = htmlspecialchars(substr((string)($s['signed_at'] ?? ''), 0, 19));
                                                        $ver = htmlspecialchars($s['doc_version'] ?? '');
                                                        if ($ver === '') { $ver = '—'; }
                                                        $auth = htmlspecialchars($s['auth_status'] ?? 'verified');
                                                        $authLabel = ucfirst($auth);
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $no . '</td>';
                                                        echo '<td style="padding:10px;">' . $signer . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $ver . '</td>';
                                                        echo '<td style="padding:10px;">' . $authLabel . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="5" style="padding:14px;">No signatures found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Record Signature</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Doc No.</label>
                                        <input id="signature-docno-input" type="text" placeholder="e.g., DOC-2026-012" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Signer Name</label>
                                        <input id="signature-signer-input" type="text" placeholder="e.g., Barangay Captain" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Document Version</label>
                                        <input id="signature-version-input" type="text" placeholder="e.g., v1.0" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Authenticity</label>
                                        <select id="signature-auth-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="verified">Verified</option>
                                            <option value="unverified">Unverified</option>
                                        </select>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="signature-submit-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="approval-monitoring-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Approval Status Monitoring</h1>
                            <p class="dashboard-subtitle">Check the current approval stage of a document.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="monitor-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Statuses</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Doc No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Title</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="approval-monitoring-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS document_routing (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        doc_no VARCHAR(100),
                                                        title VARCHAR(255) NOT NULL,
                                                        doc_type VARCHAR(100),
                                                        routed_to VARCHAR(255) DEFAULT 'Barangay Captain',
                                                        routed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                        status ENUM('Pending','Approved','Returned','Rejected') DEFAULT 'Pending',
                                                        remarks TEXT
                                                    )");
                                                    $stmtMS = $pdo->prepare("SELECT doc_no, title, doc_type, status FROM document_routing ORDER BY routed_at DESC");
                                                    $stmtMS->execute([]);
                                                    $monRows = $stmtMS->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtMS = null;
                                                } catch (Exception $e) {
                                                    $monRows = [];
                                                }
                                                if (!empty($monRows)) {
                                                    foreach ($monRows as $m) {
                                                        $no = htmlspecialchars($m['doc_no'] ?? '');
                                                        if ($no === '') { $no = '—'; }
                                                        $title = htmlspecialchars($m['title'] ?? '');
                                                        if ($title === '') { $title = '—'; }
                                                        $type = htmlspecialchars($m['doc_type'] ?? '');
                                                        if ($type === '') { $type = '—'; }
                                                        $st = htmlspecialchars($m['status'] ?? 'Pending');
                                                        $label = ucfirst($st);
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $no . '</td>';
                                                        echo '<td style="padding:10px;">' . $title . '</td>';
                                                        echo '<td style="padding:10px;">' . $type . '</td>';
                                                        echo '<td style="padding:10px;">' . $label . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="4" style="padding:14px;">No routed documents found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Update Status</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Doc No.</label>
                                        <input id="monitor-docno-input" type="text" placeholder="e.g., DOC-2026-012" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Status</label>
                                        <select id="monitor-status-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Pending">Pending</option>
                                            <option value="Approved">Approved</option>
                                            <option value="Returned">Returned for Revision</option>
                                            <option value="Rejected">Rejected</option>
                                        </select>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="monitor-submit-btn">Update</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="monthly-summary-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Monthly Records Summary</h1>
                            <p class="dashboard-subtitle">Overview of key barangay records for the selected month.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="monthly-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Summary</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Category</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Count</th>
                                            </tr>
                                        </thead>
                                        <tbody id="monthly-summary-tbody">
                                            <?php
                                                $curYear = (int)date('Y');
                                                $curMonth = (int)date('m');
                                                $ym = sprintf('%04d-%02d', $curYear, $curMonth);
                                                $cntRes = 0; $cntCert = 0; $cntComplaintsFiled = 0; $cntComplaintsResolved = 0; $cntIncidents = 0;
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_resolutions (id INT AUTO_INCREMENT PRIMARY KEY, resolution_no VARCHAR(100), title VARCHAR(255) NOT NULL, category VARCHAR(100), description TEXT, adopted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('approved','draft') DEFAULT 'approved')");
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM barangay_resolutions WHERE DATE_FORMAT(adopted_at, '%Y-%m') = ?");
                                                    $stmt->execute([$ym]);
                                                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    $cntRes = (int)($row['c'] ?? 0);
                                                    $stmt = null;
                                                } catch (Exception $e) {}
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_clearances (id INT AUTO_INCREMENT PRIMARY KEY, clearance_no VARCHAR(100), resident_name VARCHAR(255) NOT NULL, resident_address VARCHAR(255), verified_no_issues ENUM('yes','no') DEFAULT 'yes', issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, issued_by VARCHAR(255))");
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM barangay_clearances WHERE DATE_FORMAT(issued_at, '%Y-%m') = ?");
                                                    $stmt->execute([$ym]);
                                                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    $cntClear = (int)($row['c'] ?? 0);
                                                    $stmt = null;
                                                } catch (Exception $e) { $cntClear = 0; }
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS residency_certificates (id INT AUTO_INCREMENT PRIMARY KEY, certificate_no VARCHAR(100), resident_name VARCHAR(255) NOT NULL, resident_address VARCHAR(255), start_date DATE, end_date DATE, issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, issued_by VARCHAR(255))");
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM residency_certificates WHERE DATE_FORMAT(issued_at, '%Y-%m') = ?");
                                                    $stmt->execute([$ym]);
                                                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    $cntResCert = (int)($row['c'] ?? 0);
                                                    $stmt = null;
                                                } catch (Exception $e) { $cntResCert = 0; }
                                                $cntCert = $cntClear + $cntResCert;
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS incoming_communications (id INT AUTO_INCREMENT PRIMARY KEY, reference_no VARCHAR(100), sender_name VARCHAR(255) NOT NULL, sender_type VARCHAR(50), received_via VARCHAR(50), subject VARCHAR(255), summary TEXT, date_received DATE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM incoming_communications WHERE DATE_FORMAT(date_received, '%Y-%m') = ? AND LOWER(subject) LIKE '%complaint%'");
                                                    $stmt->execute([$ym]);
                                                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    $cntComplaintsFiled = (int)($row['c'] ?? 0);
                                                    $stmt = null;
                                                } catch (Exception $e) {}
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS communication_acknowledgments (id INT AUTO_INCREMENT PRIMARY KEY, direction ENUM('incoming','outgoing'), reference_no VARCHAR(100), subject VARCHAR(255), counterpart_name VARCHAR(255), date_sent_received DATE, status ENUM('acknowledged','received','pending') DEFAULT 'pending', ack_date DATE NULL, ack_by VARCHAR(255), notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM communication_acknowledgments WHERE DATE_FORMAT(ack_date, '%Y-%m') = ? AND LOWER(subject) LIKE '%complaint%' AND status = 'acknowledged'");
                                                    $stmt->execute([$ym]);
                                                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    $cntComplaintsResolved = (int)($row['c'] ?? 0);
                                                    $stmt = null;
                                                } catch (Exception $e) {}
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS outgoing_communications (id INT AUTO_INCREMENT PRIMARY KEY, reference_no VARCHAR(100), doc_type VARCHAR(100), recipient_name VARCHAR(255) NOT NULL, subject VARCHAR(255), summary TEXT, date_sent DATE, sent_by VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                                                    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM outgoing_communications WHERE DATE_FORMAT(date_sent, '%Y-%m') = ? AND LOWER(subject) LIKE '%incident%'");
                                                    $stmt->execute([$ym]);
                                                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                                                    $cntIncidents = (int)($row['c'] ?? 0);
                                                    $stmt = null;
                                                } catch (Exception $e) {}
                                                echo '<tr><td style="padding:10px;">Number of resolutions issued</td><td style="padding:10px;">' . $cntRes . '</td></tr>';
                                                echo '<tr><td style="padding:10px;">Barangay certificates released</td><td style="padding:10px;">' . $cntCert . '</td></tr>';
                                                echo '<tr><td style="padding:10px;">Complaints filed</td><td style="padding:10px;">' . $cntComplaintsFiled . '</td></tr>';
                                                echo '<tr><td style="padding:10px;">Complaints resolved</td><td style="padding:10px;">' . $cntComplaintsResolved . '</td></tr>';
                                                echo '<tr><td style="padding:10px;">Patrol/incident records logged</td><td style="padding:10px;">' . $cntIncidents . '</td></tr>';
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Filter</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Year</label>
                                        <input id="monthly-year-input" type="number" min="2000" max="2100" value="<?php echo (int)date('Y'); ?>" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Month</label>
                                        <select id="monthly-month-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <?php
                                                for ($m=1; $m<=12; $m++) {
                                                    $sel = ((int)date('m') === $m) ? ' selected' : '';
                                                    echo '<option value="' . $m . '"' . $sel . '>' . $m . '</option>';
                                                }
                                            ?>
                                        </select>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="monthly-generate-btn">Generate</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="inventory-report-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Document Inventory Report</h1>
                            <p class="dashboard-subtitle">Complete catalog of barangay documents stored in the system.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="inventory-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Inventory</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Title</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date Created</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Responsible Office</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="inventory-report-tbody">
                                            <?php
                                                $rows = [];
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_resolutions (id INT AUTO_INCREMENT PRIMARY KEY, resolution_no VARCHAR(100), title VARCHAR(255) NOT NULL, category VARCHAR(100), description TEXT, adopted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('approved','draft') DEFAULT 'approved')");
                                                    $stmt = $pdo->prepare("SELECT title, 'Resolution' AS t, adopted_at AS dt, 'Barangay Council' AS office, status FROM barangay_resolutions ORDER BY adopted_at DESC");
                                                    $stmt->execute([]);
                                                    $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                                    $stmt = null;
                                                } catch (Exception $e) {}
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_ordinances (id INT AUTO_INCREMENT PRIMARY KEY, ordinance_no VARCHAR(100), title VARCHAR(255) NOT NULL, category VARCHAR(100), description TEXT, enacted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('active','inactive') DEFAULT 'active')");
                                                    $stmt = $pdo->prepare("SELECT title, 'Ordinance' AS t, enacted_at AS dt, 'Barangay Council' AS office, status FROM barangay_ordinances ORDER BY enacted_at DESC");
                                                    $stmt->execute([]);
                                                    $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                                    $stmt = null;
                                                } catch (Exception $e) {}
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS document_drafts (id INT AUTO_INCREMENT PRIMARY KEY, doc_no VARCHAR(100), title VARCHAR(255) NOT NULL, doc_type VARCHAR(100), subject VARCHAR(255), content TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('draft','finalized') DEFAULT 'draft')");
                                                    $stmt = $pdo->prepare("SELECT title, doc_type AS t, created_at AS dt, 'Secretary' AS office, status FROM document_drafts ORDER BY created_at DESC");
                                                    $stmt->execute([]);
                                                    $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                                    $stmt = null;
                                                } catch (Exception $e) {}
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS document_archive (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, doc_type VARCHAR(100), subject VARCHAR(255), file_name VARCHAR(255), file_path VARCHAR(255), uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('uploaded','finalized','metadata_only') DEFAULT 'uploaded')");
                                                    $stmt = $pdo->prepare("SELECT title, doc_type AS t, uploaded_at AS dt, 'Secretary' AS office, status FROM document_archive ORDER BY uploaded_at DESC");
                                                    $stmt->execute([]);
                                                    $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                                    $stmt = null;
                                                } catch (Exception $e) {}
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS document_routing (id INT AUTO_INCREMENT PRIMARY KEY, doc_no VARCHAR(100), title VARCHAR(255) NOT NULL, doc_type VARCHAR(100), routed_to VARCHAR(255) DEFAULT 'Barangay Captain', routed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, status ENUM('Pending','Approved','Returned','Rejected') DEFAULT 'Pending', remarks TEXT)");
                                                    $stmt = $pdo->prepare("SELECT title, doc_type AS t, routed_at AS dt, routed_to AS office, status FROM document_routing ORDER BY routed_at DESC");
                                                    $stmt->execute([]);
                                                    $rows = array_merge($rows, $stmt->fetchAll(PDO::FETCH_ASSOC));
                                                    $stmt = null;
                                                } catch (Exception $e) {}
                                                if (!empty($rows)) {
                                                    foreach ($rows as $r) {
                                                        $title = htmlspecialchars($r['title'] ?? '');
                                                        if ($title === '') { $title = '—'; }
                                                        $type = htmlspecialchars($r['t'] ?? '');
                                                        if ($type === '') { $type = '—'; }
                                                        $dt = htmlspecialchars(substr((string)($r['dt'] ?? ''), 0, 19));
                                                        $office = htmlspecialchars($r['office'] ?? '');
                                                        if ($office === '') { $office = '—'; }
                                                        $st = htmlspecialchars($r['status'] ?? '');
                                                        if ($st === '') { $st = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $title . '</td>';
                                                        echo '<td style="padding:10px;">' . $type . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $office . '</td>';
                                                        echo '<td style="padding:10px;">' . ucfirst($st) . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="5" style="padding:14px;">No documents found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="audit-trail-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Audit Trail and Change Logs</h1>
                            <p class="dashboard-subtitle">Detailed history of actions on records and documents.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="audit-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Logs</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Action</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Entity Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Entity ID</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Description</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Actor</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date/Time</th>
                                            </tr>
                                        </thead>
                                        <tbody id="audit-trail-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (id INT AUTO_INCREMENT PRIMARY KEY, action VARCHAR(50), entity_type VARCHAR(100), entity_id VARCHAR(255), description TEXT, actor_name VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                                                    $stmtAL = $pdo->prepare("SELECT action, entity_type, entity_id, description, actor_name, created_at FROM audit_logs ORDER BY created_at DESC");
                                                    $stmtAL->execute([]);
                                                    $alRows = $stmtAL->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtAL = null;
                                                } catch (Exception $e) {
                                                    $alRows = [];
                                                }
                                                if (!empty($alRows)) {
                                                    foreach ($alRows as $a) {
                                                        $ac = htmlspecialchars($a['action'] ?? '');
                                                        if ($ac === '') { $ac = '—'; }
                                                        $et = htmlspecialchars($a['entity_type'] ?? '');
                                                        if ($et === '') { $et = '—'; }
                                                        $eid = htmlspecialchars($a['entity_id'] ?? '');
                                                        if ($eid === '') { $eid = '—'; }
                                                        $desc = htmlspecialchars($a['description'] ?? '');
                                                        if ($desc === '') { $desc = '—'; }
                                                        $actor = htmlspecialchars($a['actor_name'] ?? '');
                                                        if ($actor === '') { $actor = '—'; }
                                                        $dt = htmlspecialchars(substr((string)($a['created_at'] ?? ''), 0, 19));
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $ac . '</td>';
                                                        echo '<td style="padding:10px;">' . $et . '</td>';
                                                        echo '<td style="padding:10px;">' . $eid . '</td>';
                                                        echo '<td style="padding:10px;">' . $desc . '</td>';
                                                        echo '<td style="padding:10px;">' . $actor . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" style="padding:14px;">No audit logs found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Add Log Entry</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Action</label>
                                        <select id="audit-action-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="create">Create</option>
                                            <option value="edit">Edit</option>
                                            <option value="delete">Delete</option>
                                            <option value="approve">Approve</option>
                                            <option value="route">Route</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Entity Type</label>
                                        <input id="audit-entity-type-input" type="text" placeholder="e.g., Resolution, Ordinance, Certificate" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Entity ID</label>
                                        <input id="audit-entity-id-input" type="text" placeholder="e.g., DOC-2026-012" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Description</label>
                                        <textarea id="audit-desc-input" rows="3" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Actor Name</label>
                                        <input id="audit-actor-input" type="text" placeholder="e.g., Barangay Secretary" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="audit-submit-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="compliance-report-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Compliance and Retention Reports</h1>
                            <p class="dashboard-subtitle">Assess retention requirements and compliance with record-keeping rules.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="compliance-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Compliance Entries</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Doc No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Doc Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Requirement</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Action Recommended</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Reviewed By</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Reviewed At</th>
                                            </tr>
                                        </thead>
                                        <tbody id="compliance-report-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS retention_compliance (id INT AUTO_INCREMENT PRIMARY KEY, doc_no VARCHAR(100), doc_type VARCHAR(100), requirement TEXT, action_recommended VARCHAR(100), compliance_status ENUM('Compliant','Non-compliant') DEFAULT 'Compliant', reviewed_by VARCHAR(255), reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
                                                    $stmtRC = $pdo->prepare("SELECT doc_no, doc_type, requirement, action_recommended, compliance_status, reviewed_by, reviewed_at FROM retention_compliance ORDER BY reviewed_at DESC");
                                                    $stmtRC->execute([]);
                                                    $rcRows = $stmtRC->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtRC = null;
                                                } catch (Exception $e) {
                                                    $rcRows = [];
                                                }
                                                if (!empty($rcRows)) {
                                                    foreach ($rcRows as $r) {
                                                        $no = htmlspecialchars($r['doc_no'] ?? '');
                                                        if ($no === '') { $no = '—'; }
                                                        $type = htmlspecialchars($r['doc_type'] ?? '');
                                                        if ($type === '') { $type = '—'; }
                                                        $req = htmlspecialchars($r['requirement'] ?? '');
                                                        if ($req === '') { $req = '—'; }
                                                        $act = htmlspecialchars($r['action_recommended'] ?? '');
                                                        if ($act === '') { $act = '—'; }
                                                        $st = htmlspecialchars($r['compliance_status'] ?? 'Compliant');
                                                        $rv = htmlspecialchars($r['reviewed_by'] ?? '');
                                                        if ($rv === '') { $rv = '—'; }
                                                        $dt = htmlspecialchars(substr((string)($r['reviewed_at'] ?? ''), 0, 19));
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $no . '</td>';
                                                        echo '<td style="padding:10px;">' . $type . '</td>';
                                                        echo '<td style="padding:10px;">' . $req . '</td>';
                                                        echo '<td style="padding:10px;">' . $act . '</td>';
                                                        echo '<td style="padding:10px;">' . $st . '</td>';
                                                        echo '<td style="padding:10px;">' . $rv . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="7" style="padding:14px;">No compliance entries found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Add Compliance Record</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Doc No.</label>
                                        <input id="compliance-docno-input" type="text" placeholder="e.g., DOC-2026-012" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Doc Type</label>
                                        <input id="compliance-doctype-input" type="text" placeholder="e.g., Resolution, Certificate" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Requirement</label>
                                        <textarea id="compliance-req-input" rows="3" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Action Recommended</label>
                                        <select id="compliance-action-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="retain">Retain</option>
                                            <option value="archive">Archive</option>
                                            <option value="dispose">Dispose</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Compliance Status</label>
                                        <select id="compliance-status-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Compliant">Compliant</option>
                                            <option value="Non-compliant">Non-compliant</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Reviewed By</label>
                                        <input id="compliance-reviewedby-input" type="text" placeholder="e.g., Barangay Secretary" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="compliance-submit-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="resolution-records-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Barangay Resolution Records</h1>
                            <p class="dashboard-subtitle">Officially approved barangay resolutions.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="resolution-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Resolutions</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Title</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Category</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Adopted At</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="resolution-records-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_resolutions (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        resolution_no VARCHAR(100),
                                                        title VARCHAR(255) NOT NULL,
                                                        category VARCHAR(100),
                                                        description TEXT,
                                                        adopted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                        status ENUM('approved','draft') DEFAULT 'approved'
                                                    )");
                                                    $stmtRR = $pdo->prepare("SELECT resolution_no, title, category, adopted_at, status FROM barangay_resolutions ORDER BY adopted_at DESC");
                                                    $stmtRR->execute([]);
                                                    $resRows = $stmtRR->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtRR = null;
                                                } catch (Exception $e) {
                                                    $resRows = [];
                                                }
                                                if (!empty($resRows)) {
                                                    foreach ($resRows as $r) {
                                                        $no = htmlspecialchars($r['resolution_no'] ?? '');
                                                        if ($no === '') { $no = '—'; }
                                                        $title = htmlspecialchars($r['title'] ?? '');
                                                        if ($title === '') { $title = '—'; }
                                                        $cat = htmlspecialchars($r['category'] ?? '');
                                                        if ($cat === '') { $cat = '—'; }
                                                        $dt = htmlspecialchars(substr((string)($r['adopted_at'] ?? ''), 0, 19));
                                                        $st = htmlspecialchars($r['status'] ?? 'approved');
                                                        $label = ucfirst($st);
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $no . '</td>';
                                                        echo '<td style="padding:10px;">' . $title . '</td>';
                                                        echo '<td style="padding:10px;">' . $cat . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $label . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="5" style="padding:14px;">No resolutions found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">New Resolution</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Resolution No.</label>
                                        <input id="resolution-no-input" type="text" placeholder="e.g., BR-2026-001" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Title</label>
                                        <input id="resolution-title-input" type="text" placeholder="Title" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Category</label>
                                        <select id="resolution-category-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Community Programs">Community Programs</option>
                                            <option value="Budget Allocations">Budget Allocations</option>
                                            <option value="Agreements/Endorsements">Agreements/Endorsements</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Description</label>
                                        <textarea id="resolution-desc-input" rows="4" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="resolution-submit-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="ordinance-records-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Ordinance Records</h1>
                            <p class="dashboard-subtitle">Local laws and enforceable rules.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="ordinance-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Ordinances</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">No.</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Title</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Category</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Enacted At</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="ordinance-records-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_ordinances (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        ordinance_no VARCHAR(100),
                                                        title VARCHAR(255) NOT NULL,
                                                        category VARCHAR(100),
                                                        description TEXT,
                                                        enacted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                        status ENUM('active','repealed') DEFAULT 'active'
                                                    )");
                                                    $stmtOR = $pdo->prepare("SELECT ordinance_no, title, category, enacted_at, status FROM barangay_ordinances ORDER BY enacted_at DESC");
                                                    $stmtOR->execute([]);
                                                    $ordRows = $stmtOR->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtOR = null;
                                                } catch (Exception $e) {
                                                    $ordRows = [];
                                                }
                                                if (!empty($ordRows)) {
                                                    foreach ($ordRows as $r) {
                                                        $no = htmlspecialchars($r['ordinance_no'] ?? '');
                                                        if ($no === '') { $no = '—'; }
                                                        $title = htmlspecialchars($r['title'] ?? '');
                                                        if ($title === '') { $title = '—'; }
                                                        $cat = htmlspecialchars($r['category'] ?? '');
                                                        if ($cat === '') { $cat = '—'; }
                                                        $dt = htmlspecialchars(substr((string)($r['enacted_at'] ?? ''), 0, 19));
                                                        $st = htmlspecialchars($r['status'] ?? 'active');
                                                        $label = ucfirst($st);
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $no . '</td>';
                                                        echo '<td style="padding:10px;">' . $title . '</td>';
                                                        echo '<td style="padding:10px;">' . $cat . '</td>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $label . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="5" style="padding:14px;">No ordinances found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">New Ordinance</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Ordinance No.</label>
                                        <input id="ordinance-no-input" type="text" placeholder="e.g., BO-2026-005" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Title</label>
                                        <input id="ordinance-title-input" type="text" placeholder="Title" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Category</label>
                                        <select id="ordinance-category-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                            <option value="Curfew Hours">Curfew Hours</option>
                                            <option value="Waste Disposal Rules">Waste Disposal Rules</option>
                                            <option value="Noise Regulations">Noise Regulations</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Description</label>
                                        <textarea id="ordinance-desc-input" rows="4" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="ordinance-submit-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="exec-orders-memo-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Executive Orders & Memo</h1>
                            <p class="dashboard-subtitle">Official meeting minutes and related memos.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="minutes-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Meeting Minutes</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Time</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Attendees</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Topics</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Decisions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="minutes-records-tbody">
                                            <?php
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS meeting_minutes (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        meeting_date DATE,
                                                        meeting_time TIME,
                                                        attendees TEXT,
                                                        topics TEXT,
                                                        decisions TEXT,
                                                        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                                    )");
                                                    $stmtMM = $pdo->prepare("SELECT meeting_date, meeting_time, attendees, topics, decisions FROM meeting_minutes ORDER BY recorded_at DESC");
                                                    $stmtMM->execute([]);
                                                    $minRows = $stmtMM->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtMM = null;
                                                } catch (Exception $e) {
                                                    $minRows = [];
                                                }
                                                if (!empty($minRows)) {
                                                    foreach ($minRows as $m) {
                                                        $date = htmlspecialchars($m['meeting_date'] ?? '');
                                                        if ($date === '') { $date = '—'; }
                                                        $time = htmlspecialchars(substr((string)($m['meeting_time'] ?? ''), 0, 5));
                                                        if ($time === '') { $time = '—'; }
                                                        $att = htmlspecialchars($m['attendees'] ?? '');
                                                        if ($att === '') { $att = '—'; }
                                                        $top = htmlspecialchars($m['topics'] ?? '');
                                                        if ($top === '') { $top = '—'; }
                                                        $dec = htmlspecialchars($m['decisions'] ?? '');
                                                        if ($dec === '') { $dec = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $date . '</td>';
                                                        echo '<td style="padding:10px;">' . $time . '</td>';
                                                        echo '<td style="padding:10px;">' . $att . '</td>';
                                                        echo '<td style="padding:10px;">' . $top . '</td>';
                                                        echo '<td style="padding:10px;">' . $dec . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="5" style="padding:14px;">No meeting minutes found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">New Minutes</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div style="display:flex;gap:10px;">
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Date</label>
                                            <input id="minutes-date-input" type="date" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="display:block;margin-bottom:6px;">Time</label>
                                            <input id="minutes-time-input" type="time" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                        </div>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Attendees</label>
                                        <textarea id="minutes-attendees-input" rows="2" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Topics Discussed</label>
                                        <textarea id="minutes-topics-input" rows="3" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Decisions Made</label>
                                        <textarea id="minutes-decisions-input" rows="3" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="minutes-submit-btn">Submit</button>
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
        
        const settingsButton = document.getElementById('settings-button');
        const settingsDropdown = document.getElementById('settings-dropdown');
        if (settingsButton && settingsDropdown) {
            settingsButton.addEventListener('click', function(){
                const isOpen = settingsDropdown.classList.contains('active');
                if (isOpen) settingsDropdown.classList.remove('active'); else settingsDropdown.classList.add('active');
            });
            document.addEventListener('click', function(e){
                if (!settingsDropdown.contains(e.target) && !settingsButton.contains(e.target)) {
                    settingsDropdown.classList.remove('active');
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
                if (settingsDropdown) settingsDropdown.classList.remove('active');
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
        }
        function showSettingsSection(id){
            hideDefault();
            const sp = document.getElementById('settings-profile-section');
            const ss = document.getElementById('settings-security-section');
            if (sp) sp.style.display = 'none';
            if (ss) ss.style.display = 'none';
            const el = document.getElementById(id);
            if (el) el.style.display = 'block';
        }
        const settingsProfileBtn = document.getElementById('settings-profile-btn');
        const settingsSecurityBtn = document.getElementById('settings-security-btn');
        const sidebarSettingsProfileLink = document.getElementById('sidebar-settings-profile-link');
        const sidebarSettingsSecurityLink = document.getElementById('sidebar-settings-security-link');
        if (settingsProfileBtn) settingsProfileBtn.addEventListener('click', function(){ showSettingsSection('settings-profile-section'); if (settingsDropdown) settingsDropdown.classList.remove('active'); loadProfile(); });
        if (settingsSecurityBtn) settingsSecurityBtn.addEventListener('click', function(){ showSettingsSection('settings-security-section'); if (settingsDropdown) settingsDropdown.classList.remove('active'); });
        if (sidebarSettingsProfileLink) sidebarSettingsProfileLink.addEventListener('click', function(e){ e.preventDefault(); showSettingsSection('settings-profile-section'); loadProfile(); });
        if (sidebarSettingsSecurityLink) sidebarSettingsSecurityLink.addEventListener('click', function(e){ e.preventDefault(); showSettingsSection('settings-security-section'); });
        const settingsTabProfile = document.getElementById('settings-tab-profile');
        const settingsTabSecurity = document.getElementById('settings-tab-security');
        if (settingsTabProfile) settingsTabProfile.addEventListener('click', function(){ showSettingsSection('settings-profile-section'); loadProfile(); });
        if (settingsTabSecurity) settingsTabSecurity.addEventListener('click', function(){ showSettingsSection('settings-security-section'); });
        function openModal(id){ const el=document.getElementById(id); if(el) el.style.display='flex'; }
        function closeModal(id){ const el=document.getElementById(id); if(el) el.style.display='none'; }
        const profileForm = document.getElementById('profile-form');
        const profileStatus = document.getElementById('profile-status');
        const avatarInput = document.getElementById('profile-avatar-input');
        const avatarUploadBtn = document.getElementById('profile-avatar-upload-btn');
        const avatarStatus = document.getElementById('avatar-status');
        const avatarPreview = document.getElementById('profile-avatar-preview');
        const headerAvatar = document.querySelector('.user-avatar');
        async function loadProfile(){
            try{
                const fd = new FormData();
                fd.append('action','profile_get');
                const res = await fetch('secretary_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('secretary_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('secretary_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
        const genKeyBtn = document.getElementById('security-generate-key-btn');
        const apiStatusEl = document.getElementById('api-status');
        if (genKeyBtn && apiStatusEl) {
            genKeyBtn.addEventListener('click', async function(){
                try{
                    const fd = new FormData();
                    fd.append('action','security_generate_key');
                    const res = await fetch('secretary_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (data && data.success && data.api_key){
                        apiStatusEl.textContent = 'API key generated: ' + data.api_key.slice(0,8) + '••••••••';
                        alert('Your API key: ' + data.api_key + '\nCopy and store it securely. You will not be able to view it again.');
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
                    const res = await fetch('secretary_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
            pwdModal.innerHTML = '<div style="background:#fff;width:520px;max-width:90%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.15);"><div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #e5e7eb;"><div style="font-weight:600;">Change Password</div><button class="secondary-button" id="pwd-close">Close</button></div><form id="pwd-form" style="padding:16px;display:grid;gap:12px;"><input class="modal-input" type="password" name="current_password" placeholder="Current password" required><input class="modal-input" type="password" name="new_password" placeholder="New password" required><input class="modal-input" type="password" name="confirm_password" placeholder="Confirm new password" required><div style="display:flex;justify-content:flex-end;gap:8px;"><button type="submit" class="primary-button">Update</button></div><div id="pwd-status" style="margin-top:8px;font-weight:500;"></div></form></div>';
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
                    const res = await fetch('secretary_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
            emailModal.innerHTML = '<div style="background:#fff;width:520px;max-width:90%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.15);"><div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #e5e7eb;"><div style="font-weight:600;">Change Email</div><button class="secondary-button" id="email-close">Close</button></div><form id="email-form" style="padding:16px;display:grid;gap:12px;"><input class="modal-input" type="email" name="new_email" placeholder="New email" required><div style="display:flex;justify-content:flex-end;gap:8px;"><button type="submit" class="primary-button">Update</button></div><div id="email-status" style="margin-top:8px;font-weight:500;"></div></form></div>';
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
                    const res = await fetch('secretary_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
            delModal.innerHTML = '<div style="background:#fff;width:520px;max-width:90%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.15);"><div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #e5e7eb;"><div style="font-weight:600;">Delete Account</div><button class="secondary-button" id="del-close">Close</button></div><div style="padding:16px;display:grid;gap:12px;"><div>This action will permanently delete your account.</div><div style="display:flex;justify-content:flex-end;gap:8px;"><button class="secondary-button" id="del-confirm" style="background:#ef4444;color:#fff;border-color:#ef4444;">Confirm Delete</button></div><div id="del-status" style="margin-top:8px;font-weight:500;"></div></div></div>';
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
                    const res = await fetch('secretary_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
        
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const arrow = document.querySelector(`#${id}`).previousElementSibling.querySelector('.dropdown-arrow');
            document.querySelectorAll('.submenu').forEach(s => {
                if (s.id !== id) {
                    s.classList.remove('active');
                    const arr = s.previousElementSibling ? s.previousElementSibling.querySelector('.dropdown-arrow') : null;
                    if (arr) arr.classList.remove('rotated');
                }
            });
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
                hideDefault();
                const rr = document.getElementById('resolution-records-section');
                const or = document.getElementById('ordinance-records-section');
                const mm = document.getElementById('exec-orders-memo-section');
                const clr = document.getElementById('barangay-clearance-section');
                const res = document.getElementById('residency-certificate-section');
                const logs = document.getElementById('issued-document-logs-section');
                const ra = document.getElementById('routing-approval-section');
                const st = document.getElementById('signature-tracking-section');
                const am = document.getElementById('approval-monitoring-section');
                const dd = document.getElementById('document-drafting-section');
                const da = document.getElementById('digital-archiving-section');
                const dc = document.getElementById('document-classification-section');
                const ic = document.getElementById('incoming-comms-section');
                const ol = document.getElementById('outgoing-letters-section');
                const ar = document.getElementById('ack-receipt-section');
                const ms = document.getElementById('monthly-summary-section');
                const ir = document.getElementById('inventory-report-section');
                const at = document.getElementById('audit-trail-section');
                const cr = document.getElementById('compliance-report-section');
                if (rr) rr.style.display = 'none';
                if (or) or.style.display = 'none';
                if (mm) mm.style.display = 'none';
                if (clr) clr.style.display = 'none';
                if (res) res.style.display = 'none';
                if (logs) logs.style.display = 'none';
                if (ra) ra.style.display = 'none';
                if (st) st.style.display = 'none';
                if (am) am.style.display = 'none';
                if (dd) dd.style.display = 'none';
                if (da) da.style.display = 'none';
                if (dc) dc.style.display = 'none';
                if (ic) ic.style.display = 'none';
                if (ol) ol.style.display = 'none';
                if (ar) ar.style.display = 'none';
                if (ms) ms.style.display = 'none';
                if (ir) ir.style.display = 'none';
                if (at) at.style.display = 'none';
                if (cr) cr.style.display = 'none';
            });
        });
        
        const defaultHeader = document.querySelector('.dashboard-content > .dashboard-header');
        const statsGrid = document.querySelector('.dashboard-content > .stats-grid');
        const defaultMainGrid = document.querySelector('.dashboard-content > .main-grid');
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
                    const res = await fetch('secretary_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    document.querySelectorAll('.dashboard-content > div[id$="-section"]').forEach(s => { s.style.display = 'none'; });
                    hideDefault();
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
                    showDefault();
                });
            }
        })();
        const resolutionLink = document.getElementById('barangay-resolution-records-link');
        const ordinanceLink = document.getElementById('ordinance-records-link');
        const minutesLink = document.getElementById('exec-orders-memo-link');
        const resolutionSection = document.getElementById('resolution-records-section');
        const ordinanceSection = document.getElementById('ordinance-records-section');
        const minutesSection = document.getElementById('exec-orders-memo-section');
        const resolutionBackBtn = document.getElementById('resolution-back-btn');
        const ordinanceBackBtn = document.getElementById('ordinance-back-btn');
        const minutesBackBtn = document.getElementById('minutes-back-btn');
        const resolutionNoInput = document.getElementById('resolution-no-input');
        const resolutionTitleInput = document.getElementById('resolution-title-input');
        const resolutionCategorySelect = document.getElementById('resolution-category-select');
        const resolutionDescInput = document.getElementById('resolution-desc-input');
        const resolutionSubmitBtn = document.getElementById('resolution-submit-btn');
        const resolutionTbody = document.getElementById('resolution-records-tbody');
        const ordinanceNoInput = document.getElementById('ordinance-no-input');
        const ordinanceTitleInput = document.getElementById('ordinance-title-input');
        const ordinanceCategorySelect = document.getElementById('ordinance-category-select');
        const ordinanceDescInput = document.getElementById('ordinance-desc-input');
        const ordinanceSubmitBtn = document.getElementById('ordinance-submit-btn');
        const ordinanceTbody = document.getElementById('ordinance-records-tbody');
        const minutesDateInput = document.getElementById('minutes-date-input');
        const minutesTimeInput = document.getElementById('minutes-time-input');
        const minutesAttendeesInput = document.getElementById('minutes-attendees-input');
        const minutesTopicsInput = document.getElementById('minutes-topics-input');
        const minutesDecisionsInput = document.getElementById('minutes-decisions-input');
        const minutesSubmitBtn = document.getElementById('minutes-submit-btn');
        const minutesTbody = document.getElementById('minutes-records-tbody');
        if (resolutionLink && resolutionSection) {
            resolutionLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (ordinanceSection) ordinanceSection.style.display = 'none';
                if (minutesSection) minutesSection.style.display = 'none';
                resolutionSection.style.display = 'block';
            });
        }
        if (ordinanceLink && ordinanceSection) {
            ordinanceLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (resolutionSection) resolutionSection.style.display = 'none';
                if (minutesSection) minutesSection.style.display = 'none';
                ordinanceSection.style.display = 'block';
            });
        }
        if (minutesLink && minutesSection) {
            minutesLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (resolutionSection) resolutionSection.style.display = 'none';
                if (ordinanceSection) ordinanceSection.style.display = 'none';
                minutesSection.style.display = 'block';
            });
        }
        if (resolutionBackBtn && resolutionSection) {
            resolutionBackBtn.addEventListener('click', function(){
                resolutionSection.style.display = 'none';
                showDefault();
            });
        }
        if (ordinanceBackBtn && ordinanceSection) {
            ordinanceBackBtn.addEventListener('click', function(){
                ordinanceSection.style.display = 'none';
                showDefault();
            });
        }
        if (minutesBackBtn && minutesSection) {
            minutesBackBtn.addEventListener('click', function(){
                minutesSection.style.display = 'none';
                showDefault();
            });
        }
        function appendResolutionRow(rec){
            if (!resolutionTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.resolution_no || '—'));
            tr.appendChild(tdWith(rec.title || '—'));
            tr.appendChild(tdWith(rec.category || '—'));
            tr.appendChild(tdWith(rec.adopted_at || new Date().toISOString().substring(0,19).replace('T',' ')));
            tr.appendChild(tdWith(rec.status ? rec.status.charAt(0).toUpperCase()+rec.status.slice(1) : 'Approved'));
            resolutionTbody.insertBefore(tr, resolutionTbody.firstChild);
        }
        function appendOrdinanceRow(rec){
            if (!ordinanceTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.ordinance_no || '—'));
            tr.appendChild(tdWith(rec.title || '—'));
            tr.appendChild(tdWith(rec.category || '—'));
            tr.appendChild(tdWith(rec.enacted_at || new Date().toISOString().substring(0,19).replace('T',' ')));
            tr.appendChild(tdWith(rec.status ? rec.status.charAt(0).toUpperCase()+rec.status.slice(1) : 'Active'));
            ordinanceTbody.insertBefore(tr, ordinanceTbody.firstChild);
        }
        function appendMinutesRow(rec){
            if (!minutesTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.meeting_date || '—'));
            tr.appendChild(tdWith(rec.meeting_time || '—'));
            tr.appendChild(tdWith(rec.attendees || '—'));
            tr.appendChild(tdWith(rec.topics || '—'));
            tr.appendChild(tdWith(rec.decisions || '—'));
            minutesTbody.insertBefore(tr, minutesTbody.firstChild);
        }
        if (resolutionSubmitBtn) {
            resolutionSubmitBtn.addEventListener('click', function(){
                const no = resolutionNoInput ? resolutionNoInput.value.trim() : '';
                const title = resolutionTitleInput ? resolutionTitleInput.value.trim() : '';
                const cat = resolutionCategorySelect ? resolutionCategorySelect.value.trim() : '';
                const desc = resolutionDescInput ? resolutionDescInput.value.trim() : '';
                if (!title) return;
                const body = new URLSearchParams();
                body.set('create_resolution', '1');
                body.set('resolution_no', no);
                body.set('title', title);
                body.set('category', cat);
                body.set('description', desc);
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendResolutionRow(data.record);
                            if (resolutionNoInput) resolutionNoInput.value = '';
                            if (resolutionTitleInput) resolutionTitleInput.value = '';
                            if (resolutionDescInput) resolutionDescInput.value = '';
                        } else {
                            alert('Failed to create resolution.');
                        }
                    }).catch(()=>{ alert('Failed to create resolution.'); });
            });
        }
        if (ordinanceSubmitBtn) {
            ordinanceSubmitBtn.addEventListener('click', function(){
                const no = ordinanceNoInput ? ordinanceNoInput.value.trim() : '';
                const title = ordinanceTitleInput ? ordinanceTitleInput.value.trim() : '';
                const cat = ordinanceCategorySelect ? ordinanceCategorySelect.value.trim() : '';
                const desc = ordinanceDescInput ? ordinanceDescInput.value.trim() : '';
                if (!title) return;
                const body = new URLSearchParams();
                body.set('create_ordinance', '1');
                body.set('ordinance_no', no);
                body.set('title', title);
                body.set('category', cat);
                body.set('description', desc);
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendOrdinanceRow(data.record);
                            if (ordinanceNoInput) ordinanceNoInput.value = '';
                            if (ordinanceTitleInput) ordinanceTitleInput.value = '';
                            if (ordinanceDescInput) ordinanceDescInput.value = '';
                        } else {
                            alert('Failed to create ordinance.');
                        }
                    }).catch(()=>{ alert('Failed to create ordinance.'); });
            });
        }
        if (minutesSubmitBtn) {
            minutesSubmitBtn.addEventListener('click', function(){
                const date = minutesDateInput ? minutesDateInput.value.trim() : '';
                const time = minutesTimeInput ? minutesTimeInput.value.trim() : '';
                const att = minutesAttendeesInput ? minutesAttendeesInput.value.trim() : '';
                const top = minutesTopicsInput ? minutesTopicsInput.value.trim() : '';
                const dec = minutesDecisionsInput ? minutesDecisionsInput.value.trim() : '';
                if (!date || !time) return;
                const body = new URLSearchParams();
                body.set('create_minutes', '1');
                body.set('meeting_date', date);
                body.set('meeting_time', time);
                body.set('attendees', att);
                body.set('topics', top);
                body.set('decisions', dec);
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendMinutesRow(data.record);
                            if (minutesDateInput) minutesDateInput.value = '';
                            if (minutesTimeInput) minutesTimeInput.value = '';
                            if (minutesAttendeesInput) minutesAttendeesInput.value = '';
                            if (minutesTopicsInput) minutesTopicsInput.value = '';
                            if (minutesDecisionsInput) minutesDecisionsInput.value = '';
                        } else {
                            alert('Failed to save minutes.');
                        }
                    }).catch(()=>{ alert('Failed to save minutes.'); });
            });
        }
        const clearanceLink = document.getElementById('barangay-clearance-link');
        const residencyLink = document.getElementById('residency-certificate-link');
        const logsLink = document.getElementById('issued-document-logs-link');
        const clearanceSection = document.getElementById('barangay-clearance-section');
        const residencySection = document.getElementById('residency-certificate-section');
        const logsSection = document.getElementById('issued-document-logs-section');
        const clearanceBackBtn = document.getElementById('clearance-back-btn');
        const residencyBackBtn = document.getElementById('residency-back-btn');
        const logsBackBtn = document.getElementById('logs-back-btn');
        if (clearanceLink && clearanceSection) {
            clearanceLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (residencySection) residencySection.style.display = 'none';
                if (logsSection) logsSection.style.display = 'none';
                clearanceSection.style.display = 'block';
            });
        }
        if (residencyLink && residencySection) {
            residencyLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (clearanceSection) clearanceSection.style.display = 'none';
                if (logsSection) logsSection.style.display = 'none';
                residencySection.style.display = 'block';
            });
        }
        if (logsLink && logsSection) {
            logsLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (clearanceSection) clearanceSection.style.display = 'none';
                if (residencySection) residencySection.style.display = 'none';
                logsSection.style.display = 'block';
            });
        }
        if (clearanceBackBtn && clearanceSection) {
            clearanceBackBtn.addEventListener('click', function(){
                clearanceSection.style.display = 'none';
                showDefault();
            });
        }
        if (residencyBackBtn && residencySection) {
            residencyBackBtn.addEventListener('click', function(){
                residencySection.style.display = 'none';
                showDefault();
            });
        }
        if (logsBackBtn && logsSection) {
            logsBackBtn.addEventListener('click', function(){
                logsSection.style.display = 'none';
                showDefault();
            });
        }
        const clearanceNoInput = document.getElementById('clearance-no-input');
        const clearanceNameInput = document.getElementById('clearance-name-input');
        const clearanceAddressInput = document.getElementById('clearance-address-input');
        const clearanceVerifiedSelect = document.getElementById('clearance-verified-select');
        const clearanceDateInput = document.getElementById('clearance-date-input');
        const clearanceIssuedByInput = document.getElementById('clearance-issuedby-input');
        const clearanceSubmitBtn = document.getElementById('clearance-submit-btn');
        const clearanceTbody = document.getElementById('clearance-records-tbody');
        function appendClearanceRow(rec){
            if (!clearanceTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.clearance_no || '—'));
            tr.appendChild(tdWith(rec.resident_name || '—'));
            tr.appendChild(tdWith(rec.resident_address || '—'));
            tr.appendChild(tdWith(rec.verified_no_issues==='no'?'No':'Yes'));
            tr.appendChild(tdWith(rec.issued_at || new Date().toISOString().substring(0,19).replace('T',' ')));
            tr.appendChild(tdWith(rec.issued_by || '—'));
            clearanceTbody.insertBefore(tr, clearanceTbody.firstChild);
        }
        if (clearanceSubmitBtn) {
            clearanceSubmitBtn.addEventListener('click', function(){
                const no = clearanceNoInput ? clearanceNoInput.value.trim() : '';
                const name = clearanceNameInput ? clearanceNameInput.value.trim() : '';
                const addr = clearanceAddressInput ? clearanceAddressInput.value.trim() : '';
                const ver = clearanceVerifiedSelect ? clearanceVerifiedSelect.value.trim() : 'yes';
                const dateIssued = clearanceDateInput ? clearanceDateInput.value.trim() : '';
                const issuedBy = clearanceIssuedByInput ? clearanceIssuedByInput.value.trim() : '';
                if (!name) return;
                const body = new URLSearchParams();
                body.set('issue_clearance', '1');
                body.set('clearance_no', no);
                body.set('resident_name', name);
                body.set('resident_address', addr);
                body.set('verified_no_issues', ver);
                body.set('date_issued', dateIssued);
                body.set('issued_by', issuedBy);
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendClearanceRow(data.record);
                            if (clearanceNoInput) clearanceNoInput.value = '';
                            if (clearanceNameInput) clearanceNameInput.value = '';
                            if (clearanceAddressInput) clearanceAddressInput.value = '';
                            if (clearanceDateInput) clearanceDateInput.value = '';
                            if (clearanceIssuedByInput) clearanceIssuedByInput.value = '';
                        } else {
                            alert('Failed to issue clearance.');
                        }
                    }).catch(()=>{ alert('Failed to issue clearance.'); });
            });
        }
        const residencyNoInput = document.getElementById('residency-no-input');
        const residencyNameInput = document.getElementById('residency-name-input');
        const residencyAddressInput = document.getElementById('residency-address-input');
        const residencyStartInput = document.getElementById('residency-start-input');
        const residencyEndInput = document.getElementById('residency-end-input');
        const residencyDateInput = document.getElementById('residency-date-input');
        const residencyIssuedByInput = document.getElementById('residency-issuedby-input');
        const residencySubmitBtn = document.getElementById('residency-submit-btn');
        const residencyTbody = document.getElementById('residency-records-tbody');
        function appendResidencyRow(rec){
            if (!residencyTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.certificate_no || '—'));
            tr.appendChild(tdWith(rec.resident_name || '—'));
            tr.appendChild(tdWith(rec.resident_address || '—'));
            const period = (rec.start_date && rec.end_date) ? (rec.start_date + ' to ' + rec.end_date) : '—';
            tr.appendChild(tdWith(period));
            tr.appendChild(tdWith(rec.issued_at || new Date().toISOString().substring(0,19).replace('T',' ')));
            tr.appendChild(tdWith(rec.issued_by || '—'));
            residencyTbody.insertBefore(tr, residencyTbody.firstChild);
        }
        if (residencySubmitBtn) {
            residencySubmitBtn.addEventListener('click', function(){
                const no = residencyNoInput ? residencyNoInput.value.trim() : '';
                const name = residencyNameInput ? residencyNameInput.value.trim() : '';
                const addr = residencyAddressInput ? residencyAddressInput.value.trim() : '';
                const sd = residencyStartInput ? residencyStartInput.value.trim() : '';
                const ed = residencyEndInput ? residencyEndInput.value.trim() : '';
                const dateIssued = residencyDateInput ? residencyDateInput.value.trim() : '';
                const issuedBy = residencyIssuedByInput ? residencyIssuedByInput.value.trim() : '';
                if (!name) return;
                const body = new URLSearchParams();
                body.set('issue_residency', '1');
                body.set('certificate_no', no);
                body.set('resident_name', name);
                body.set('resident_address', addr);
                body.set('start_date', sd);
                body.set('end_date', ed);
                body.set('date_issued', dateIssued);
                body.set('issued_by', issuedBy);
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendResidencyRow(data.record);
                            if (residencyNoInput) residencyNoInput.value = '';
                            if (residencyNameInput) residencyNameInput.value = '';
                            if (residencyAddressInput) residencyAddressInput.value = '';
                            if (residencyStartInput) residencyStartInput.value = '';
                            if (residencyEndInput) residencyEndInput.value = '';
                            if (residencyDateInput) residencyDateInput.value = '';
                            if (residencyIssuedByInput) residencyIssuedByInput.value = '';
                        } else {
                            alert('Failed to issue residency certificate.');
                        }
                    }).catch(()=>{ alert('Failed to issue residency certificate.'); });
            });
        }
        const issuedLogForm = document.getElementById('issued-log-form');
        const issuedLogsTbody = document.getElementById('issued-logs-tbody');
        function appendIssuedLogRow(rec){
            if (!issuedLogsTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.doc_type || '—'));
            tr.appendChild(tdWith(rec.doc_no || '—'));
            tr.appendChild(tdWith(rec.recipient_name || '—'));
            tr.appendChild(tdWith(rec.date_issued || '—'));
            tr.appendChild(tdWith(rec.issued_by || '—'));
            tr.appendChild(tdWith(rec.logged_at || new Date().toISOString().substring(0,19).replace('T',' ')));
            issuedLogsTbody.insertBefore(tr, issuedLogsTbody.firstChild);
        }
        if (issuedLogForm) {
            issuedLogForm.addEventListener('submit', function(e){
                e.preventDefault();
                const fd = new URLSearchParams();
                fd.set('create_issued_log', '1');
                fd.set('doc_type', document.getElementById('issued-type-select')?.value.trim() || '');
                fd.set('doc_no', document.getElementById('issued-docno-input')?.value.trim() || '');
                fd.set('recipient_name', document.getElementById('issued-recipient-input')?.value.trim() || '');
                fd.set('date_issued', document.getElementById('issued-date-input')?.value.trim() || '');
                fd.set('issued_by', document.getElementById('issued-by-input')?.value.trim() || '');
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: fd.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendIssuedLogRow(data.record);
                            issuedLogForm.reset();
                        } else {
                            alert('Failed to add log entry.');
                        }
                    }).catch(()=>{ alert('Failed to add log entry.'); });
            });
        }
        const draftingLink = document.getElementById('document-drafting-link');
        const archivingLink = document.getElementById('digital-archiving-link');
        const classificationLink = document.getElementById('document-classification-link');
        const draftingSection = document.getElementById('document-drafting-section');
        const archivingSection = document.getElementById('digital-archiving-section');
        const classificationSection = document.getElementById('document-classification-section');
        const draftingBackBtn = document.getElementById('drafting-back-btn');
        const archivingBackBtn = document.getElementById('archiving-back-btn');
        const classificationBackBtn = document.getElementById('classification-back-btn');
        if (draftingLink && draftingSection) {
            draftingLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (archivingSection) archivingSection.style.display = 'none';
                if (classificationSection) classificationSection.style.display = 'none';
                draftingSection.style.display = 'block';
            });
        }
        if (archivingLink && archivingSection) {
            archivingLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (draftingSection) draftingSection.style.display = 'none';
                if (classificationSection) classificationSection.style.display = 'none';
                archivingSection.style.display = 'block';
            });
        }
        if (classificationLink && classificationSection) {
            classificationLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (draftingSection) draftingSection.style.display = 'none';
                if (archivingSection) archivingSection.style.display = 'none';
                classificationSection.style.display = 'block';
            });
        }
        if (draftingBackBtn && draftingSection) {
            draftingBackBtn.addEventListener('click', function(){
                draftingSection.style.display = 'none';
                showDefault();
            });
        }
        if (archivingBackBtn && archivingSection) {
            archivingBackBtn.addEventListener('click', function(){
                archivingSection.style.display = 'none';
                showDefault();
            });
        }
        if (classificationBackBtn && classificationSection) {
            classificationBackBtn.addEventListener('click', function(){
                classificationSection.style.display = 'none';
                showDefault();
            });
        }
        const draftDocNoInput = document.getElementById('draft-docno-input');
        const draftTitleInput = document.getElementById('draft-title-input');
        const draftTypeSelect = document.getElementById('draft-type-select');
        const draftSubjectInput = document.getElementById('draft-subject-input');
        const draftContentInput = document.getElementById('draft-content-input');
        const draftSubmitBtn = document.getElementById('draft-submit-btn');
        const documentDraftsTbody = document.getElementById('document-drafts-tbody');
        function appendDraftRow(rec){
            if (!documentDraftsTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.doc_no || '—'));
            tr.appendChild(tdWith(rec.title || '—'));
            tr.appendChild(tdWith(rec.doc_type || '—'));
            tr.appendChild(tdWith(rec.subject || '—'));
            tr.appendChild(tdWith(rec.created_at || new Date().toISOString().substring(0,19).replace('T',' ')));
            tr.appendChild(tdWith(rec.status ? rec.status.charAt(0).toUpperCase()+rec.status.slice(1) : 'Draft'));
            documentDraftsTbody.insertBefore(tr, documentDraftsTbody.firstChild);
        }
        if (draftSubmitBtn) {
            draftSubmitBtn.addEventListener('click', function(){
                const docno = draftDocNoInput ? draftDocNoInput.value.trim() : '';
                const title = draftTitleInput ? draftTitleInput.value.trim() : '';
                const type = draftTypeSelect ? draftTypeSelect.value.trim() : '';
                const subject = draftSubjectInput ? draftSubjectInput.value.trim() : '';
                const content = draftContentInput ? draftContentInput.value.trim() : '';
                if (!title) return;
                const body = new URLSearchParams();
                body.set('save_draft', '1');
                body.set('doc_no', docno);
                body.set('title', title);
                body.set('doc_type', type);
                body.set('subject', subject);
                body.set('content', content);
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendDraftRow(data.record);
                            if (draftDocNoInput) draftDocNoInput.value = '';
                            if (draftTitleInput) draftTitleInput.value = '';
                            if (draftSubjectInput) draftSubjectInput.value = '';
                            if (draftContentInput) draftContentInput.value = '';
                        } else {
                            alert('Failed to save draft.');
                        }
                    }).catch(()=>{ alert('Failed to save draft.'); });
            });
        }
        const archiveForm = document.getElementById('archive-upload-form');
        const archiveTbody = document.getElementById('archive-records-tbody');
        function appendArchiveRow(rec){
            if (!archiveTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.title || '—'));
            tr.appendChild(tdWith(rec.doc_type || '—'));
            tr.appendChild(tdWith(rec.subject || '—'));
            tr.appendChild(tdWith(rec.file_name || '—'));
            tr.appendChild(tdWith(rec.uploaded_at || new Date().toISOString().substring(0,19).replace('T',' ')));
            tr.appendChild(tdWith(rec.status ? rec.status.charAt(0).toUpperCase()+rec.status.slice(1) : 'Uploaded'));
            archiveTbody.insertBefore(tr, archiveTbody.firstChild);
        }
        if (archiveForm) {
            archiveForm.addEventListener('submit', function(e){
                e.preventDefault();
                const fd = new FormData(archiveForm);
                fd.set('archive_upload', '1');
                fetch(window.location.href, { method:'POST', body: fd })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendArchiveRow(data.record);
                            archiveForm.reset();
                        } else {
                            alert('Failed to upload document.');
                        }
                    }).catch(()=>{ alert('Failed to upload document.'); });
            });
        }
        const classYearInput = document.getElementById('class-year-input');
        const classMonthInput = document.getElementById('class-month-input');
        const classDayInput = document.getElementById('class-day-input');
        const classTypeSelect = document.getElementById('class-type-select');
        const classSubjectInput = document.getElementById('class-subject-input');
        const classTitleInput = document.getElementById('class-title-input');
        const classSubmitBtn = document.getElementById('class-submit-btn');
        const classificationTbody = document.getElementById('classification-records-tbody');
        function appendClassificationRow(rec){
            if (!classificationTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith((rec.date_year||'—').toString()));
            tr.appendChild(tdWith((rec.date_month||'—').toString()));
            tr.appendChild(tdWith((rec.date_day||'—').toString()));
            tr.appendChild(tdWith(rec.doc_type || '—'));
            tr.appendChild(tdWith(rec.subject || '—'));
            tr.appendChild(tdWith(rec.document_title || '—'));
            classificationTbody.insertBefore(tr, classificationTbody.firstChild);
        }
        if (classSubmitBtn) {
            classSubmitBtn.addEventListener('click', function(){
                const y = classYearInput ? classYearInput.value.trim() : '';
                const m = classMonthInput ? classMonthInput.value.trim() : '';
                const d = classDayInput ? classDayInput.value.trim() : '';
                const type = classTypeSelect ? classTypeSelect.value.trim() : '';
                const subject = classSubjectInput ? classSubjectInput.value.trim() : '';
                const title = classTitleInput ? classTitleInput.value.trim() : '';
                if (!title) return;
                const body = new URLSearchParams();
                body.set('create_classification', '1');
                body.set('date_year', y);
                body.set('date_month', m);
                body.set('date_day', d);
                body.set('doc_type', type);
                body.set('subject', subject);
                body.set('document_title', title);
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendClassificationRow(data.record);
                            if (classYearInput) classYearInput.value = '';
                            if (classMonthInput) classMonthInput.value = '';
                            if (classDayInput) classDayInput.value = '';
                            if (classSubjectInput) classSubjectInput.value = '';
                            if (classTitleInput) classTitleInput.value = '';
                        } else {
                            alert('Failed to add classification.');
                        }
                    }).catch(()=>{ alert('Failed to add classification.'); });
            });
        }
        const incomingLink = document.getElementById('incoming-comms-link');
        const outgoingLink = document.getElementById('outgoing-letters-link');
        const ackLink = document.getElementById('ack-receipt-link');
        const incomingSection = document.getElementById('incoming-comms-section');
        const outgoingSection = document.getElementById('outgoing-letters-section');
        const ackSection = document.getElementById('ack-receipt-section');
        const incomingBackBtn = document.getElementById('incoming-back-btn');
        const outgoingBackBtn = document.getElementById('outgoing-back-btn');
        const ackBackBtn = document.getElementById('ack-back-btn');
        if (incomingLink && incomingSection) {
            incomingLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (outgoingSection) outgoingSection.style.display = 'none';
                if (ackSection) ackSection.style.display = 'none';
                incomingSection.style.display = 'block';
            });
        }
        if (outgoingLink && outgoingSection) {
            outgoingLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (incomingSection) incomingSection.style.display = 'none';
                if (ackSection) ackSection.style.display = 'none';
                outgoingSection.style.display = 'block';
            });
        }
        if (ackLink && ackSection) {
            ackLink.addEventListener('click', function(e){
                e.preventDefault();
                hideDefault();
                if (incomingSection) incomingSection.style.display = 'none';
                if (outgoingSection) outgoingSection.style.display = 'none';
                ackSection.style.display = 'block';
            });
        }
        if (incomingBackBtn && incomingSection) {
            incomingBackBtn.addEventListener('click', function(){
                incomingSection.style.display = 'none';
                showDefault();
            });
        }
        if (outgoingBackBtn && outgoingSection) {
            outgoingBackBtn.addEventListener('click', function(){
                outgoingSection.style.display = 'none';
                showDefault();
            });
        }
        if (ackBackBtn && ackSection) {
            ackBackBtn.addEventListener('click', function(){
                ackSection.style.display = 'none';
                showDefault();
            });
        }
        const incomingRefInput = document.getElementById('incoming-ref-input');
        const incomingSenderInput = document.getElementById('incoming-sender-input');
        const incomingTypeSelect = document.getElementById('incoming-type-select');
        const incomingViaSelect = document.getElementById('incoming-via-select');
        const incomingSubjectInput = document.getElementById('incoming-subject-input');
        const incomingSummaryInput = document.getElementById('incoming-summary-input');
        const incomingDateInput = document.getElementById('incoming-date-input');
        const incomingSubmitBtn = document.getElementById('incoming-submit-btn');
        const incomingTbody = document.getElementById('incoming-comms-tbody');
        function appendIncomingRow(rec){
            if (!incomingTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.reference_no || '—'));
            tr.appendChild(tdWith(rec.sender_name || '—'));
            tr.appendChild(tdWith(rec.sender_type || '—'));
            tr.appendChild(tdWith(rec.received_via || '—'));
            tr.appendChild(tdWith(rec.subject || '—'));
            tr.appendChild(tdWith(rec.summary || '—'));
            tr.appendChild(tdWith(rec.date_received || '—'));
            incomingTbody.insertBefore(tr, incomingTbody.firstChild);
        }
        if (incomingSubmitBtn) {
            incomingSubmitBtn.addEventListener('click', function(){
                const ref = incomingRefInput ? incomingRefInput.value.trim() : '';
                const sender = incomingSenderInput ? incomingSenderInput.value.trim() : '';
                const stype = incomingTypeSelect ? incomingTypeSelect.value.trim() : '';
                const via = incomingViaSelect ? incomingViaSelect.value.trim() : '';
                const subj = incomingSubjectInput ? incomingSubjectInput.value.trim() : '';
                const sum = incomingSummaryInput ? incomingSummaryInput.value.trim() : '';
                const date = incomingDateInput ? incomingDateInput.value.trim() : '';
                if (!sender || !subj) return;
                const body = new URLSearchParams();
                body.set('record_incoming', '1');
                body.set('reference_no', ref);
                body.set('sender_name', sender);
                body.set('sender_type', stype);
                body.set('received_via', via);
                body.set('subject', subj);
                body.set('summary', sum);
                body.set('date_received', date);
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendIncomingRow(data.record);
                            if (incomingRefInput) incomingRefInput.value = '';
                            if (incomingSenderInput) incomingSenderInput.value = '';
                            if (incomingSubjectInput) incomingSubjectInput.value = '';
                            if (incomingSummaryInput) incomingSummaryInput.value = '';
                            if (incomingDateInput) incomingDateInput.value = '';
                        } else {
                            alert('Failed to record incoming communication.');
                        }
                    }).catch(()=>{ alert('Failed to record incoming communication.'); });
            });
        }
        const outgoingRefInput = document.getElementById('outgoing-ref-input');
        const outgoingTypeSelect = document.getElementById('outgoing-type-select');
        const outgoingRecipientInput = document.getElementById('outgoing-recipient-input');
        const outgoingSubjectInput = document.getElementById('outgoing-subject-input');
        const outgoingSummaryInput = document.getElementById('outgoing-summary-input');
        const outgoingDateInput = document.getElementById('outgoing-date-input');
        const outgoingSentByInput = document.getElementById('outgoing-sentby-input');
        const outgoingSubmitBtn = document.getElementById('outgoing-submit-btn');
        const outgoingTbody = document.getElementById('outgoing-letters-tbody');
        function appendOutgoingRow(rec){
            if (!outgoingTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.reference_no || '—'));
            tr.appendChild(tdWith(rec.doc_type || '—'));
            tr.appendChild(tdWith(rec.recipient_name || '—'));
            tr.appendChild(tdWith(rec.subject || '—'));
            tr.appendChild(tdWith(rec.summary || '—'));
            tr.appendChild(tdWith(rec.date_sent || '—'));
            tr.appendChild(tdWith(rec.sent_by || '—'));
            outgoingTbody.insertBefore(tr, outgoingTbody.firstChild);
        }
        if (outgoingSubmitBtn) {
            outgoingSubmitBtn.addEventListener('click', function(){
                const ref = outgoingRefInput ? outgoingRefInput.value.trim() : '';
                const type = outgoingTypeSelect ? outgoingTypeSelect.value.trim() : '';
                const rec = outgoingRecipientInput ? outgoingRecipientInput.value.trim() : '';
                const subj = outgoingSubjectInput ? outgoingSubjectInput.value.trim() : '';
                const sum = outgoingSummaryInput ? outgoingSummaryInput.value.trim() : '';
                const date = outgoingDateInput ? outgoingDateInput.value.trim() : '';
                const by = outgoingSentByInput ? outgoingSentByInput.value.trim() : '';
                if (!rec || !subj) return;
                const body = new URLSearchParams();
                body.set('record_outgoing', '1');
                body.set('reference_no', ref);
                body.set('doc_type', type);
                body.set('recipient_name', rec);
                body.set('subject', subj);
                body.set('summary', sum);
                body.set('date_sent', date);
                body.set('sent_by', by);
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendOutgoingRow(data.record);
                            if (outgoingRefInput) outgoingRefInput.value = '';
                            if (outgoingRecipientInput) outgoingRecipientInput.value = '';
                            if (outgoingSubjectInput) outgoingSubjectInput.value = '';
                            if (outgoingSummaryInput) outgoingSummaryInput.value = '';
                            if (outgoingDateInput) outgoingDateInput.value = '';
                            if (outgoingSentByInput) outgoingSentByInput.value = '';
                        } else {
                            alert('Failed to record outgoing communication.');
                        }
                    }).catch(()=>{ alert('Failed to record outgoing communication.'); });
            });
        }
        const ackDirectionSelect = document.getElementById('ack-direction-select');
        const ackRefInput = document.getElementById('ack-ref-input');
        const ackSubjectInput = document.getElementById('ack-subject-input');
        const ackCounterpartInput = document.getElementById('ack-counterpart-input');
        const ackDateSRInput = document.getElementById('ack-date-sr-input');
        const ackStatusSelect = document.getElementById('ack-status-select');
        const ackDateInput = document.getElementById('ack-date-input');
        const ackByInput = document.getElementById('ack-by-input');
        const ackNotesInput = document.getElementById('ack-notes-input');
        const ackSubmitBtn = document.getElementById('ack-submit-btn');
        const ackTbody = document.getElementById('ack-receipt-tbody');
        function appendAckRow(rec){
            if (!ackTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            tr.appendChild(tdWith(rec.direction || '—'));
            tr.appendChild(tdWith(rec.reference_no || '—'));
            tr.appendChild(tdWith(rec.subject || '—'));
            tr.appendChild(tdWith(rec.counterpart_name || '—'));
            tr.appendChild(tdWith(rec.date_sent_received || '—'));
            const status = rec.status ? (rec.status.charAt(0).toUpperCase()+rec.status.slice(1)) : 'Pending';
            tr.appendChild(tdWith(status));
            tr.appendChild(tdWith(rec.ack_date || '—'));
            tr.appendChild(tdWith(rec.ack_by || '—'));
            ackTbody.insertBefore(tr, ackTbody.firstChild);
        }
        if (ackSubmitBtn) {
            ackSubmitBtn.addEventListener('click', function(){
                const direction = ackDirectionSelect ? ackDirectionSelect.value.trim() : '';
                const ref = ackRefInput ? ackRefInput.value.trim() : '';
                const subj = ackSubjectInput ? ackSubjectInput.value.trim() : '';
                const cp = ackCounterpartInput ? ackCounterpartInput.value.trim() : '';
                const date = ackDateSRInput ? ackDateSRInput.value.trim() : '';
                const status = ackStatusSelect ? ackStatusSelect.value.trim() : '';
                const ad = ackDateInput ? ackDateInput.value.trim() : '';
                const ab = ackByInput ? ackByInput.value.trim() : '';
                const notes = ackNotesInput ? ackNotesInput.value.trim() : '';
                if (!direction || !cp) return;
                const body = new URLSearchParams();
                body.set('record_ack', '1');
                body.set('direction', direction);
                body.set('reference_no', ref);
                body.set('subject', subj);
                body.set('counterpart_name', cp);
                body.set('date_sent_received', date);
                body.set('status', status);
                body.set('ack_date', ad);
                body.set('ack_by', ab);
                body.set('notes', notes);
                fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                    .then(r=>r.json()).then(data=>{
                        if (data && data.ok && data.record) {
                            appendAckRow(data.record);
                            if (ackRefInput) ackRefInput.value = '';
                            if (ackSubjectInput) ackSubjectInput.value = '';
                            if (ackCounterpartInput) ackCounterpartInput.value = '';
                            if (ackDateSRInput) ackDateSRInput.value = '';
                            if (ackDateInput) ackDateInput.value = '';
                            if (ackByInput) ackByInput.value = '';
                            if (ackNotesInput) ackNotesInput.value = '';
                        } else {
                            alert('Failed to add tracking entry.');
                        }
                    }).catch(()=>{ alert('Failed to add tracking entry.'); });
            });
        }
                const routingLink = document.getElementById('document-routing-link');
                const signatureLink = document.getElementById('signature-tracking-link');
                const monitoringLink = document.getElementById('approval-monitoring-link');
                const routingSection = document.getElementById('routing-approval-section');
                const signatureSection = document.getElementById('signature-tracking-section');
                const monitoringSection = document.getElementById('approval-monitoring-section');
                const routingBackBtn = document.getElementById('routing-back-btn');
                const signatureBackBtn = document.getElementById('signature-back-btn');
                const monitorBackBtn = document.getElementById('monitor-back-btn');
                if (routingLink && routingSection) {
                    routingLink.addEventListener('click', function(e){
                        e.preventDefault();
                        hideDefault();
                        if (signatureSection) signatureSection.style.display = 'none';
                        if (monitoringSection) monitoringSection.style.display = 'none';
                        routingSection.style.display = 'block';
                    });
                }
                if (signatureLink && signatureSection) {
                    signatureLink.addEventListener('click', function(e){
                        e.preventDefault();
                        hideDefault();
                        if (routingSection) routingSection.style.display = 'none';
                        if (monitoringSection) monitoringSection.style.display = 'none';
                        signatureSection.style.display = 'block';
                    });
                }
                if (monitoringLink && monitoringSection) {
                    monitoringLink.addEventListener('click', function(e){
                        e.preventDefault();
                        hideDefault();
                        if (routingSection) routingSection.style.display = 'none';
                        if (signatureSection) signatureSection.style.display = 'none';
                        monitoringSection.style.display = 'block';
                    });
                }
                if (routingBackBtn && routingSection) {
                    routingBackBtn.addEventListener('click', function(){
                        routingSection.style.display = 'none';
                        showDefault();
                    });
                }
                if (signatureBackBtn && signatureSection) {
                    signatureBackBtn.addEventListener('click', function(){
                        signatureSection.style.display = 'none';
                        showDefault();
                    });
                }
                if (monitorBackBtn && monitoringSection) {
                    monitorBackBtn.addEventListener('click', function(){
                        monitoringSection.style.display = 'none';
                        showDefault();
                    });
                }
                const monthlyLink = document.getElementById('monthly-summary-link');
                const inventoryLink = document.getElementById('inventory-report-link');
                const auditLink = document.getElementById('audit-trail-link');
                const complianceLink = document.getElementById('compliance-report-link');
                const monthlySection = document.getElementById('monthly-summary-section');
                const inventorySection = document.getElementById('inventory-report-section');
                const auditSection = document.getElementById('audit-trail-section');
                const complianceSection = document.getElementById('compliance-report-section');
                const monthlyBackBtn = document.getElementById('monthly-back-btn');
                const inventoryBackBtn = document.getElementById('inventory-back-btn');
                const auditBackBtn = document.getElementById('audit-back-btn');
                const complianceBackBtn = document.getElementById('compliance-back-btn');
                if (monthlyLink && monthlySection) {
                    monthlyLink.addEventListener('click', function(e){
                        e.preventDefault();
                        hideDefault();
                        if (inventorySection) inventorySection.style.display = 'none';
                        if (auditSection) auditSection.style.display = 'none';
                        if (complianceSection) complianceSection.style.display = 'none';
                        monthlySection.style.display = 'block';
                    });
                }
                if (inventoryLink && inventorySection) {
                    inventoryLink.addEventListener('click', function(e){
                        e.preventDefault();
                        hideDefault();
                        if (monthlySection) monthlySection.style.display = 'none';
                        if (auditSection) auditSection.style.display = 'none';
                        if (complianceSection) complianceSection.style.display = 'none';
                        inventorySection.style.display = 'block';
                    });
                }
                if (auditLink && auditSection) {
                    auditLink.addEventListener('click', function(e){
                        e.preventDefault();
                        hideDefault();
                        if (monthlySection) monthlySection.style.display = 'none';
                        if (inventorySection) inventorySection.style.display = 'none';
                        if (complianceSection) complianceSection.style.display = 'none';
                        auditSection.style.display = 'block';
                    });
                }
                if (complianceLink && complianceSection) {
                    complianceLink.addEventListener('click', function(e){
                        e.preventDefault();
                        hideDefault();
                        if (monthlySection) monthlySection.style.display = 'none';
                        if (inventorySection) inventorySection.style.display = 'none';
                        if (auditSection) auditSection.style.display = 'none';
                        complianceSection.style.display = 'block';
                    });
                }
                if (monthlyBackBtn && monthlySection) {
                    monthlyBackBtn.addEventListener('click', function(){
                        monthlySection.style.display = 'none';
                        showDefault();
                    });
                }
                if (inventoryBackBtn && inventorySection) {
                    inventoryBackBtn.addEventListener('click', function(){
                        inventorySection.style.display = 'none';
                        showDefault();
                    });
                }
                if (auditBackBtn && auditSection) {
                    auditBackBtn.addEventListener('click', function(){
                        auditSection.style.display = 'none';
                        showDefault();
                    });
                }
                if (complianceBackBtn && complianceSection) {
                    complianceBackBtn.addEventListener('click', function(){
                        complianceSection.style.display = 'none';
                        showDefault();
                    });
                }
                const monthlyYearInput = document.getElementById('monthly-year-input');
                const monthlyMonthSelect = document.getElementById('monthly-month-select');
                const monthlyGenerateBtn = document.getElementById('monthly-generate-btn');
                const monthlySummaryTbody = document.getElementById('monthly-summary-tbody');
                function renderMonthlySummary(rec){
                    if (!monthlySummaryTbody) return;
                    monthlySummaryTbody.innerHTML = '';
                    function trPair(label, value){
                        const tr = document.createElement('tr');
                        const td1 = document.createElement('td'); td1.style.padding='10px'; td1.textContent = label;
                        const td2 = document.createElement('td'); td2.style.padding='10px'; td2.textContent = String(value);
                        tr.appendChild(td1); tr.appendChild(td2);
                        return tr;
                    }
                    monthlySummaryTbody.appendChild(trPair('Number of resolutions issued', rec.resolutions || 0));
                    monthlySummaryTbody.appendChild(trPair('Barangay certificates released', rec.certificates || 0));
                    monthlySummaryTbody.appendChild(trPair('Complaints filed', rec.complaints_filed || 0));
                    monthlySummaryTbody.appendChild(trPair('Complaints resolved', rec.complaints_resolved || 0));
                    monthlySummaryTbody.appendChild(trPair('Patrol/incident records logged', rec.incidents_logged || 0));
                }
                if (monthlyGenerateBtn) {
                    monthlyGenerateBtn.addEventListener('click', function(){
                        const y = monthlyYearInput ? monthlyYearInput.value.trim() : '';
                        const m = monthlyMonthSelect ? monthlyMonthSelect.value.trim() : '';
                        if (!y || !m) return;
                        const body = new URLSearchParams();
                        body.set('generate_monthly_summary', '1');
                        body.set('year', y);
                        body.set('month', m);
                        fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                            .then(r=>r.json()).then(data=>{
                                if (data && data.ok && data.record) {
                                    renderMonthlySummary(data.record);
                                } else {
                                    alert('Failed to generate summary.');
                                }
                            }).catch(()=>{ alert('Failed to generate summary.'); });
                    });
                }
                const auditActionSelect = document.getElementById('audit-action-select');
                const auditEntityTypeInput = document.getElementById('audit-entity-type-input');
                const auditEntityIdInput = document.getElementById('audit-entity-id-input');
                const auditDescInput = document.getElementById('audit-desc-input');
                const auditActorInput = document.getElementById('audit-actor-input');
                const auditSubmitBtn = document.getElementById('audit-submit-btn');
                const auditTbody = document.getElementById('audit-trail-tbody');
                function appendAuditRow(rec){
                    if (!auditTbody) return;
                    const tr = document.createElement('tr');
                    function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                    tr.appendChild(tdWith(rec.action || '—'));
                    tr.appendChild(tdWith(rec.entity_type || '—'));
                    tr.appendChild(tdWith(rec.entity_id || '—'));
                    tr.appendChild(tdWith(rec.description || '—'));
                    tr.appendChild(tdWith(rec.actor_name || '—'));
                    tr.appendChild(tdWith(rec.created_at || new Date().toISOString().substring(0,19).replace('T',' ')));
                    auditTbody.insertBefore(tr, auditTbody.firstChild);
                }
                if (auditSubmitBtn) {
                    auditSubmitBtn.addEventListener('click', function(){
                        const action = auditActionSelect ? auditActionSelect.value.trim() : '';
                        const etype = auditEntityTypeInput ? auditEntityTypeInput.value.trim() : '';
                        const eid = auditEntityIdInput ? auditEntityIdInput.value.trim() : '';
                        const desc = auditDescInput ? auditDescInput.value.trim() : '';
                        const actor = auditActorInput ? auditActorInput.value.trim() : '';
                        if (!action || !etype || !eid || !actor) return;
                        const body = new URLSearchParams();
                        body.set('add_audit_log', '1');
                        body.set('action', action);
                        body.set('entity_type', etype);
                        body.set('entity_id', eid);
                        body.set('description', desc);
                        body.set('actor_name', actor);
                        fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                            .then(r=>r.json()).then(data=>{
                                if (data && data.ok && data.record) {
                                    appendAuditRow(data.record);
                                    if (auditEntityTypeInput) auditEntityTypeInput.value = '';
                                    if (auditEntityIdInput) auditEntityIdInput.value = '';
                                    if (auditDescInput) auditDescInput.value = '';
                                    if (auditActorInput) auditActorInput.value = '';
                                } else {
                                    alert('Failed to add log entry.');
                                }
                            }).catch(()=>{ alert('Failed to add log entry.'); });
                    });
                }
                const complianceDocNoInput = document.getElementById('compliance-docno-input');
                const complianceDocTypeInput = document.getElementById('compliance-doctype-input');
                const complianceReqInput = document.getElementById('compliance-req-input');
                const complianceActionSelect = document.getElementById('compliance-action-select');
                const complianceStatusSelect = document.getElementById('compliance-status-select');
                const complianceReviewedByInput = document.getElementById('compliance-reviewedby-input');
                const complianceSubmitBtn = document.getElementById('compliance-submit-btn');
                const complianceTbody = document.getElementById('compliance-report-tbody');
                function appendComplianceRow(rec){
                    if (!complianceTbody) return;
                    const tr = document.createElement('tr');
                    function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                    tr.appendChild(tdWith(rec.doc_no || '—'));
                    tr.appendChild(tdWith(rec.doc_type || '—'));
                    tr.appendChild(tdWith(rec.requirement || '—'));
                    tr.appendChild(tdWith(rec.action_recommended || '—'));
                    tr.appendChild(tdWith(rec.compliance_status || 'Compliant'));
                    tr.appendChild(tdWith(rec.reviewed_by || '—'));
                    tr.appendChild(tdWith(rec.reviewed_at || new Date().toISOString().substring(0,19).replace('T',' ')));
                    complianceTbody.insertBefore(tr, complianceTbody.firstChild);
                }
                if (complianceSubmitBtn) {
                    complianceSubmitBtn.addEventListener('click', function(){
                        const docno = complianceDocNoInput ? complianceDocNoInput.value.trim() : '';
                        const dtype = complianceDocTypeInput ? complianceDocTypeInput.value.trim() : '';
                        const req = complianceReqInput ? complianceReqInput.value.trim() : '';
                        const act = complianceActionSelect ? complianceActionSelect.value.trim() : '';
                        const status = complianceStatusSelect ? complianceStatusSelect.value.trim() : '';
                        const reviewer = complianceReviewedByInput ? complianceReviewedByInput.value.trim() : '';
                        if (!docno || !dtype || !req || !act || !reviewer) return;
                        const body = new URLSearchParams();
                        body.set('add_compliance', '1');
                        body.set('doc_no', docno);
                        body.set('doc_type', dtype);
                        body.set('requirement', req);
                        body.set('action_recommended', act);
                        body.set('compliance_status', status);
                        body.set('reviewed_by', reviewer);
                        fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                            .then(r=>r.json()).then(data=>{
                                if (data && data.ok && data.record) {
                                    appendComplianceRow(data.record);
                                    if (complianceDocNoInput) complianceDocNoInput.value = '';
                                    if (complianceDocTypeInput) complianceDocTypeInput.value = '';
                                    if (complianceReqInput) complianceReqInput.value = '';
                                    if (complianceReviewedByInput) complianceReviewedByInput.value = '';
                                } else {
                                    alert('Failed to add compliance record.');
                                }
                            }).catch(()=>{ alert('Failed to add compliance record.'); });
                    });
                }
                const routeDocNoInput = document.getElementById('route-docno-input');
                const routeTitleInput = document.getElementById('route-title-input');
                const routeTypeSelect = document.getElementById('route-type-select');
                const routeRemarksInput = document.getElementById('route-remarks-input');
                const routeSubmitBtn = document.getElementById('route-submit-btn');
                const routingTbody = document.getElementById('routing-approval-tbody');
                function appendRoutingRow(rec){
                    if (!routingTbody) return;
                    const tr = document.createElement('tr');
                    function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                    tr.appendChild(tdWith(rec.doc_no || '—'));
                    tr.appendChild(tdWith(rec.title || '—'));
                    tr.appendChild(tdWith(rec.doc_type || '—'));
                    tr.appendChild(tdWith(rec.routed_to || 'Barangay Captain'));
                    tr.appendChild(tdWith(rec.routed_at || new Date().toISOString().substring(0,19).replace('T',' ')));
                    const status = rec.status ? rec.status.charAt(0).toUpperCase()+rec.status.slice(1).toLowerCase() : 'Pending';
                    tr.appendChild(tdWith(status));
                    routingTbody.insertBefore(tr, routingTbody.firstChild);
                }
                if (routeSubmitBtn) {
                    routeSubmitBtn.addEventListener('click', function(){
                        const docno = routeDocNoInput ? routeDocNoInput.value.trim() : '';
                        const title = routeTitleInput ? routeTitleInput.value.trim() : '';
                        const type = routeTypeSelect ? routeTypeSelect.value.trim() : '';
                        const remarks = routeRemarksInput ? routeRemarksInput.value.trim() : '';
                        if (!title || !type) return;
                        const body = new URLSearchParams();
                        body.set('route_document', '1');
                        body.set('doc_no', docno);
                        body.set('title', title);
                        body.set('doc_type', type);
                        body.set('remarks', remarks);
                        fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                            .then(r=>r.json()).then(data=>{
                                if (data && data.ok && data.record) {
                                    appendRoutingRow(data.record);
                                    if (routeDocNoInput) routeDocNoInput.value = '';
                                    if (routeTitleInput) routeTitleInput.value = '';
                                    if (routeRemarksInput) routeRemarksInput.value = '';
                                } else {
                                    alert('Failed to route document.');
                                }
                            }).catch(()=>{ alert('Failed to route document.'); });
                    });
                }
                const signatureDocNoInput = document.getElementById('signature-docno-input');
                const signatureSignerInput = document.getElementById('signature-signer-input');
                const signatureVersionInput = document.getElementById('signature-version-input');
                const signatureAuthSelect = document.getElementById('signature-auth-select');
                const signatureSubmitBtn = document.getElementById('signature-submit-btn');
                const signatureTbody = document.getElementById('signature-tracking-tbody');
                function appendSignatureRow(rec){
                    if (!signatureTbody) return;
                    const tr = document.createElement('tr');
                    function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                    tr.appendChild(tdWith(rec.doc_no || '—'));
                    tr.appendChild(tdWith(rec.signer_name || '—'));
                    tr.appendChild(tdWith(rec.signed_at || new Date().toISOString().substring(0,19).replace('T',' ')));
                    tr.appendChild(tdWith(rec.doc_version || '—'));
                    const auth = rec.auth_status ? rec.auth_status.charAt(0).toUpperCase()+rec.auth_status.slice(1) : 'Verified';
                    tr.appendChild(tdWith(auth));
                    signatureTbody.insertBefore(tr, signatureTbody.firstChild);
                }
                if (signatureSubmitBtn) {
                    signatureSubmitBtn.addEventListener('click', function(){
                        const docno = signatureDocNoInput ? signatureDocNoInput.value.trim() : '';
                        const signer = signatureSignerInput ? signatureSignerInput.value.trim() : '';
                        const version = signatureVersionInput ? signatureVersionInput.value.trim() : '';
                        const auth = signatureAuthSelect ? signatureAuthSelect.value.trim() : 'verified';
                        if (!docno || !signer) return;
                        const body = new URLSearchParams();
                        body.set('add_signature', '1');
                        body.set('doc_no', docno);
                        body.set('signer_name', signer);
                        body.set('doc_version', version);
                        body.set('auth_status', auth);
                        fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                            .then(r=>r.json()).then(data=>{
                                if (data && data.ok && data.record) {
                                    appendSignatureRow(data.record);
                                    if (signatureDocNoInput) signatureDocNoInput.value = '';
                                    if (signatureSignerInput) signatureSignerInput.value = '';
                                    if (signatureVersionInput) signatureVersionInput.value = '';
                                } else {
                                    alert('Failed to add signature.');
                                }
                            }).catch(()=>{ alert('Failed to add signature.'); });
                    });
                }
                const monitorDocNoInput = document.getElementById('monitor-docno-input');
                const monitorStatusSelect = document.getElementById('monitor-status-select');
                const monitorSubmitBtn = document.getElementById('monitor-submit-btn');
                const monitoringTbody = document.getElementById('approval-monitoring-tbody');
                function updateMonitoringRow(rec){
                    if (!monitoringTbody) return;
                    const rows = monitoringTbody.querySelectorAll('tr');
                    let updated = false;
                    rows.forEach(function(row){
                        const firstTd = row.querySelector('td');
                        if (firstTd && firstTd.textContent === (rec.doc_no || '')) {
                            const tds = row.querySelectorAll('td');
                            const statusLabel = rec.status ? rec.status.charAt(0).toUpperCase()+rec.status.slice(1) : 'Pending';
                            if (tds.length >= 4) { tds[3].textContent = statusLabel; }
                            updated = true;
                        }
                    });
                    if (!updated) {
                        const tr = document.createElement('tr');
                        function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                        tr.appendChild(tdWith(rec.doc_no || '—'));
                        tr.appendChild(tdWith('—'));
                        tr.appendChild(tdWith('—'));
                        const statusLabel = rec.status ? rec.status.charAt(0).toUpperCase()+rec.status.slice(1) : 'Pending';
                        tr.appendChild(tdWith(statusLabel));
                        monitoringTbody.insertBefore(tr, monitoringTbody.firstChild);
                    }
                }
                if (monitorSubmitBtn) {
                    monitorSubmitBtn.addEventListener('click', function(){
                        const docno = monitorDocNoInput ? monitorDocNoInput.value.trim() : '';
                        const status = monitorStatusSelect ? monitorStatusSelect.value.trim() : '';
                        if (!docno || !status) return;
                        const body = new URLSearchParams();
                        body.set('update_approval_status', '1');
                        body.set('doc_no', docno);
                        body.set('status', status);
                        fetch(window.location.href, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
                            .then(r=>r.json()).then(data=>{
                                if (data && data.ok && data.record) {
                                    updateMonitoringRow(data.record);
                                    if (monitorDocNoInput) monitorDocNoInput.value = '';
                                } else {
                                    alert('Failed to update status.');
                                }
                            }).catch(()=>{ alert('Failed to update status.'); });
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
    </script>
</body>
</html>
