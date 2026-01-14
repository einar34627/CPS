<?php

session_start();
require_once '../config/db_connection.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}


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
    $role = "USER";
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
        $watch_stmt = $pdo->prepare("SELECT id, first_name, middle_name, last_name, email, role, is_verified FROM users WHERE role LIKE ?");
        $watch_stmt->execute(['%WATCH%']);
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

    $complaints = [
        ['id'=>201,'resident'=>'Juan Dela Cruz','issue'=>'Noise disturbance','category'=>'Nuisance','location'=>'Zone 1, Street A','submitted_at'=>'2025-11-27 20:15','status'=>'Pending'],
        ['id'=>202,'resident'=>'Maria Santos','issue'=>'Garbage not collected','category'=>'Sanitation','location'=>'Zone 3, Street C','submitted_at'=>'2025-11-26 10:05','status'=>'In Review'],
        ['id'=>203,'resident'=>'Pedro Reyes','issue'=>'Unauthorized parking','category'=>'Traffic','location'=>'Zone 2, Street B','submitted_at'=>'2025-11-25 08:30','status'=>'Resolved'],
    ];

    $complaint_analytics = [];
    foreach ($complaints as $c) {
        $cat = isset($c['category']) ? $c['category'] : 'General';
        $loc = isset($c['location']) ? $c['location'] : '—';
        $k = $cat.'|'.$loc;
        if (!isset($complaint_analytics[$k])) {
            $concern = $cat.' • '.$loc;
            $complaint_analytics[$k] = ['cat'=>$cat,'loc'=>$loc,'concern'=>$concern,'reports'=>0,'pending'=>0,'resolved'=>0,'last'=>''];
        }
        $complaint_analytics[$k]['reports']++;
        $st = strtolower($c['status'] ?? '');
        if ($st === 'resolved') { $complaint_analytics[$k]['resolved']++; } else { $complaint_analytics[$k]['pending']++; }
        $sa = $c['submitted_at'] ?? '';
        if ($sa && (!$complaint_analytics[$k]['last'] || strtotime($sa) > strtotime($complaint_analytics[$k]['last']))) { $complaint_analytics[$k]['last'] = $sa; }
    }
    $complaint_analytics_rows = array_values($complaint_analytics);

    $volunteers = [
        ['id'=>301,'name'=>'Juan Dela Cruz','role'=>'Volunteer','contact'=>'+63 912 345 6789','email'=>'juan@example.com','zone'=>'Zone 1','availability'=>'Evenings'],
        ['id'=>302,'name'=>'Maria Santos','role'=>'Tanod','contact'=>'+63 917 555 1212','email'=>'maria@example.com','zone'=>'Zone 3','availability'=>'Weekends'],
        ['id'=>303,'name'=>'Pedro Reyes','role'=>'Volunteer','contact'=>'+63 915 222 7788','email'=>'pedro@example.com','zone'=>'Zone 2','availability'=>'M-F'],
    ];
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
            
<<<<<<< HEAD
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
            <a href="#" class="submenu-item">Route Mapping</a>
            <a href="#" class="submenu-item">GPS Tracking</a>
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
            <a href="#" class="submenu-item">Verification</a>
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
        
        <a href="#" class="menu-item">
            <div class="icon-box icon-bg-indigo">
                <i class='bx bxs-help-circle icon-indigo'></i>
            </div>
            <span class="font-medium">Help</span>
        </a>
        
        <a href="../includes/logout.php" class="menu-item">
            <div class="icon-box icon-bg-red">
                <i class='bx bx-log-out icon-red'></i>
            </div>
            <span class="font-medium">Logout</span>
        </a>
    </div>
</div>
=======
            <!-- Menu Section -->
            <div class="menu-section">
                <p class="menu-title">FIRE & RESCUE MANAGEMENT</p>
                
                <div class="menu-items">
                    <a href="#" class="menu-item active" id="dashboard-menu">
                        <div class="icon-box icon-bg-red">
                            <i class='bx bxs-dashboard icon-red'></i>
                        </div>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    
                    <!-- User Management -->
                    <div class="menu-item" onclick="toggleSubmenu('user-management')">
                        <div class="icon-box icon-bg-orange">
                            <i class='bx bxs-user icon-orange'></i>
                        </div>
                        <span class="font-medium">User Management</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="user-management" class="submenu">
                        <a href="#" class="submenu-item">Manage Users</a>
                        <a href="#" class="submenu-item">Role Control</a>
                        <a href="#" class="submenu-item">Monitor Activity</a>
                        <a href="#" class="submenu-item">Reset Passwords</a>
                    </div>
                    
                    <!-- Fire & Incident Reporting Management -->
                    <div class="menu-item" onclick="toggleSubmenu('incident-management')">
                        <div class="icon-box icon-bg-yellow">
                            <i class='bx bxs-alarm-exclamation icon-yellow'></i>
                        </div>
                        <span class="font-medium">Incident Management</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="incident-management" class="submenu">
                        <a href="#" class="submenu-item">View Reports</a>
                        <a href="#" class="submenu-item">Validate Data</a>
                        <a href="#" class="submenu-item">Assign Severity</a>
                        <a href="#" class="submenu-item">Track Progress</a>
                        <a href="#" class="submenu-item">Mark Resolved</a>
                    </div>
                    
                    <!-- Barangay Volunteer Roster Management -->
                    <div class="menu-item" onclick="toggleSubmenu('volunteer-management')">
                        <div class="icon-box icon-bg-blue">
                            <i class='bx bxs-user-detail icon-blue'></i>
                        </div>
                        <span class="font-medium">Volunteer Management</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="volunteer-management" class="submenu">
                        <a href="vm/review_data.php" class="submenu-item">Review Data</a>
                        <a href="#" class="submenu-item">Approve Applications</a>
                        <a href="#" class="submenu-item">Assign Volunteers</a>
                        <a href="#" class="submenu-item">View Availability</a>
                        <a href="#" class="submenu-item">Remove Volunteers</a>
                        <a href="vm/toggle_volunteer_registration.php" class="submenu-item">Toggle Volunteer Registration Access</a>
                    </div>
                    
                    <!-- Resource Inventory Management -->
                    <div class="menu-item" onclick="toggleSubmenu('resource-management')">
                        <div class="icon-box icon-bg-green">
                            <i class='bx bxs-cube icon-green'></i>
                        </div>
                        <span class="font-medium">Resource Management</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="resource-management" class="submenu">
                        <a href="#" class="submenu-item">View Equipment</a>
                        <a href="#" class="submenu-item">Approve Maintenance</a>
                        <a href="#" class="submenu-item">Approve Resources</a>
                        <a href="#" class="submenu-item">Review Deployment</a>
                    </div>
                    
                    <!-- Shift & Duty Scheduling -->
                    <div class="menu-item" onclick="toggleSubmenu('schedule-management')">
                        <div class="icon-box icon-bg-purple">
                            <i class='bx bxs-calendar icon-purple'></i>
                        </div>
                        <span class="font-medium">Schedule Management</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="schedule-management" class="submenu">
                        <a href="#" class="submenu-item">Create Schedule</a>
                        <a href="#" class="submenu-item">Approve Shifts</a>
                        <a href="#" class="submenu-item">Override Assignments</a>
                        <a href="#" class="submenu-item">Monitor Attendance</a>
                    </div>
                    
                    <!-- Training & Certification Monitoring -->
                    <div class="menu-item" onclick="toggleSubmenu('training-management')">
                        <div class="icon-box icon-bg-teal">
                            <i class='bx bxs-graduation icon-teal'></i>
                        </div>
                        <span class="font-medium">Training Management</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="training-management" class="submenu">
                        <a href="#" class="submenu-item">View Records</a>
                        <a href="#" class="submenu-item">Approve Completions</a>
                        <a href="#" class="submenu-item">Assign Training</a>
                        <a href="#" class="submenu-item">Track Expiry</a>
                    </div>
                    
                    <!-- Inspection Logs for Establishments -->
                    <div class="menu-item" onclick="toggleSubmenu('inspection-management')">
                        <div class="icon-box icon-bg-cyan">
                            <i class='bx bxs-check-shield icon-cyan'></i>
                        </div>
                        <span class="font-medium">Inspection Management</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="inspection-management" class="submenu">
                        <a href="#" class="submenu-item">Approve Reports</a>
                        <a href="#" class="submenu-item">Review Violations</a>
                        <a href="#" class="submenu-item">Issue Certificates</a>
                        <a href="#" class="submenu-item">Track Follow-Up</a>
                    </div>
                    
                    <!-- Post-Incident Reporting & Analytics -->
                    <div class="menu-item" onclick="toggleSubmenu('analytics-management')">
                        <div class="icon-box icon-bg-pink">
                            <i class='bx bxs-file-doc icon-pink'></i>
                        </div>
                        <span class="font-medium">Analytics & Reports</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="analytics-management" class="submenu">
                        <a href="#" class="submenu-item">Review Summaries</a>
                        <a href="#" class="submenu-item">Analyze Data</a>
                        <a href="#" class="submenu-item">Export Reports</a>
                        <a href="#" class="submenu-item">Generate Statistics</a>
                    </div>
                    
                   
                </div>
                
                <p class="menu-title" style="margin-top: 32px;">GENERAL</p>
                
                <div class="menu-items">
                    <a href="#" class="menu-item">
                        <div class="icon-box icon-bg-teal">
                            <i class='bx bxs-cog icon-teal'></i>
                        </div>
                        <span class="font-medium">Settings</span>
                    </a>
                    
                    <a href="../profile.php" class="menu-item">
                        <div class="icon-box icon-bg-orange">
                            <i class='bx bxs-user icon-orange'></i>
                        </div>
                        <span class="font-medium">Profile</span>
                    </a>
                    
                    <a href="../includes/logout.php" class="menu-item">
                        <div class="icon-box icon-bg-red">
                            <i class='bx bx-log-out icon-red'></i>
                        </div>
                        <span class="font-medium">Logout</span>
                    </a>
                </div>
            </div>
>>>>>>> 2d4df041c7a7f7ce738cd38352724fc924273484
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
                             <img src="../img/rei.jfif" alt="User" class="user-avatar">
                            <div class="user-info">
                                <p class="user-name"><?php echo $full_name; ?></p>
                                <p class="user-email"><?php echo $role; ?></p>
                          </div>
                        </div>
                    </div>
                </div>
            </div>
            
<<<<<<< HEAD
            <div class="dashboard-content content-section" id="home-section">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Community & Surveillance Dashboard</h1>
                        <p class="dashboard-subtitle">Monitor, manage, and coordinate community & surveillance operations.</p>
=======
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Administrative Dashboard</h1>
                        <p class="dashboard-subtitle">Oversee, approve, configure, and analyze the system.</p>
>>>>>>> 2d4df041c7a7f7ce738cd38352724fc924273484
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
                                    $resident = htmlspecialchars($c['resident'] ?? '');
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
                                    $resident = htmlspecialchars($c['resident'] ?? '');
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
                                <tr class="volreg-row" data-id="<?php echo $id; ?>" data-name="<?php echo $name; ?>" data-role="<?php echo $roleLabel; ?>" data-contact="<?php echo $contact; ?>" data-email="<?php echo $email; ?>" data-zone="<?php echo $zone; ?>" data-availability="<?php echo $avail; ?>">
                                    <td><?php echo $name; ?></td>
                                    <td><?php echo $roleLabel; ?></td>
                                    <td><?php echo $contact; ?></td>
                                    <td><?php echo $email; ?></td>
                                    <td><?php echo $zone; ?></td>
                                    <td><?php echo $avail; ?></td>
                                    <td class="assign-controls"><button class="primary-button volreg-view-btn">View</button></td>
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
                        <button class="secondary-button" id="route-back">Back to Dashboard</button>
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
                    <iframe id="gps-tracking-frame" src="GPS%20Tracking.php" title="GPS Tracking" scrolling="no" style="width:100%;min-height:720px;border:0;border-radius:12px;background:transparent;"></iframe>
                </div>
            </div>
            <div class="content-section" id="summary-report-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Summary Reports</div>
                        <button class="secondary-button" id="summary-back">Back to Dashboard</button>
                    </div>
                    <iframe id="summary-report-frame" src="Summary%20Report.php" title="Summary Reports" scrolling="no" style="width:100%;min-height:720px;border:0;border-radius:12px;background:transparent;"></iframe>
                </div>
            </div>
            <div class="content-section" id="registration-system-section">
                <div class="assign-card">
                    <div class="registry-header">
                        <div class="registry-title">Registration System</div>
                        <button class="secondary-button" id="registration-back">Back to Dashboard</button>
                    </div>
                    <iframe id="registration-system-frame" src="Registratiom%20System.php" title="Registration System" scrolling="no" style="width:100%;min-height:720px;border:0;border-radius:12px;background:transparent;"></iframe>
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
                    <iframe id="feedback-frame" src="Feedback.php" title="Feedback" scrolling="no" style="width:100%;min-height:720px;border:0;border-radius:12px;background:transparent;"></iframe>
                </div>
            </div>
            <div class="content-section" id="settings-profile-section">
                <div class="settings-card">
                    <div class="settings-title">Profile</div>
                    <div class="settings-list">
                        <div class="settings-item">
                            <div class="settings-item-left">
                                <div class="settings-item-icon"><i class='bx bxs-user'></i></div>
                                <div>
                                    <div class="settings-item-title">Display Name</div>
                                    <div class="settings-item-desc"><?php echo $full_name; ?></div>
                                </div>
                            </div>
                            <button class="secondary-button" id="profile-edit-btn">Edit</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-section" id="settings-security-section">
                <div class="settings-card">
                    <div class="settings-nav">
                        <span class="settings-tab">Profile</span>
                        <span class="settings-tab active">Security</span>
                        <span class="settings-tab">Admin Tools</span>
                    </div>
                    <div class="settings-title">Security</div>
                    <div class="settings-list">
                        <div class="settings-item">
                            <div class="settings-item-left">
                                <div class="settings-item-icon"><i class='bx bxs-lock-alt'></i></div>
                                <div>
                                    <div class="settings-item-title">Change Password</div>
                                    <div class="settings-item-desc" id="pwd-last-changed">Last changed 3 months ago</div>
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
                                <div class="settings-item-icon"><i class='bx bxs-shield'></i></div>
                                <div>
                                    <div class="settings-item-title">Two-Factor Authentication</div>
                                    <div class="settings-item-desc" id="tfa-status"><span class="badge badge-pending">Disabled</span></div>
                                </div>
                            </div>
                            <button class="secondary-button" id="security-enable-2fa-btn">Enable</button>
                        </div>
                    </div>
                    <div class="settings-danger">
                        <div class="settings-danger-title"><i class='bx bxs-error'></i> Danger Zone</div>
                        <div class="settings-item-desc">Once you delete your account, there is no going back. Please be certain.</div>
                        <div style="margin-top:8px;">
                            <button class="secondary-button" id="security-delete-account-btn" style="background:#ef4444;color:#fff;border-color:#ef4444;">Delete Account</button>
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
            volregTable.addEventListener('click', function(e){
                const row = e.target.closest('.volreg-row');
                if (!row) return;
                const panel = document.getElementById('volreg-details');
                panel.style.display = 'block';
                const name = row.getAttribute('data-name');
                const role = row.getAttribute('data-role');
                const contact = row.getAttribute('data-contact');
                const email = row.getAttribute('data-email');
                const zone = row.getAttribute('data-zone');
                const avail = row.getAttribute('data-availability');
                panel.innerHTML = `<div><div style=\"font-weight:600;font-size:16px;\">${name}</div><div style=\"color:#6b7280;font-size:14px;\">${role} • ${zone} • ${avail}</div><div style=\"margin-top:10px;\">Contact: ${contact}</div><div style=\"margin-top:10px;\">Email: ${email}</div></div>`;
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
        
        const genKeyBtn = document.getElementById('security-generate-key-btn');
        const apiStatusEl = document.getElementById('api-status');
        if (genKeyBtn && apiStatusEl) {
            genKeyBtn.addEventListener('click', function(){
                const key = 'sk_' + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
                apiStatusEl.textContent = 'API key generated: ' + key.slice(0,8) + '••••••••';
            });
        }
        const tfaBtn = document.getElementById('security-enable-2fa-btn');
        const tfaStatusEl = document.getElementById('tfa-status');
        if (tfaBtn && tfaStatusEl) {
            tfaBtn.addEventListener('click', function(){
                tfaStatusEl.innerHTML = '<span class="badge badge-active">Enabled</span>';
                tfaBtn.textContent = 'Disable';
            });
        }
        const pwdBtn = document.getElementById('security-change-password-btn');
        const pwdLastChanged = document.getElementById('pwd-last-changed');
        if (pwdBtn && pwdLastChanged) {
            pwdBtn.addEventListener('click', function(){
                pwdLastChanged.textContent = 'Just changed • Demo';
            });
        }
        const emailBtn = document.getElementById('security-change-email-btn');
        if (emailBtn) {
            emailBtn.addEventListener('click', function(){
                alert('Email change UI coming soon');
            });
        }
        const delBtn = document.getElementById('security-delete-account-btn');
        if (delBtn) {
            delBtn.addEventListener('click', function(){
                const ok = confirm('Are you sure you want to delete your account? This action cannot be undone.');
                if (ok) { alert('Account deletion request submitted (demo)'); }
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
                    row.setAttribute('data-status','Resolved');
                    const stCell = row.querySelector('td:nth-child(4)');
                    if (stCell) stCell.innerHTML = '<span class="badge badge-resolved">Resolved</span>';
                    updateCounts();
                    const rid = row.getAttribute('data-id');
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
