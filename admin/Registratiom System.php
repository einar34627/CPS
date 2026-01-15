<?php
session_start();

require_once '../config/db_connection.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

function set_registration_flash(string $type, string $message): void
{
    $_SESSION['registration_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_full_name(array $user = null): string
{
    if (!$user) {
        return 'System User';
    }

    $parts = [
        $user['first_name'] ?? '',
        $user['middle_name'] ?? '',
        $user['last_name'] ?? ''
    ];

    return trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($parts, fn($part) => !empty($part)))));
}

$currentUserId = (int) $_SESSION['user_id'];
$currentUserStmt = $pdo->prepare("SELECT first_name, middle_name, last_name, role FROM users WHERE id = ?");
$currentUserStmt->execute([$currentUserId]);
$currentUser = $currentUserStmt->fetch();

$currentUserName = get_full_name($currentUser);
$currentUserRole = $currentUser['role'] ?? 'ADMIN';

if (empty($_SESSION['registration_csrf'])) {
    $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['registration_csrf'];
$flash = $_SESSION['registration_flash'] ?? null;
unset($_SESSION['registration_flash']);

$allowedRoles = ['ADMIN', 'EMPLOYEE', 'USER'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        set_registration_flash('error', 'Security validation failed. Please refresh the page and try again.');
        header("Location: Registratiom System.php");
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $firstName = sanitize_input($_POST['first_name'] ?? '');
        $middleName = sanitize_input($_POST['middle_name'] ?? '');
        $lastName = sanitize_input($_POST['last_name'] ?? '');
        $username = sanitize_input($_POST['username'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $contact = sanitize_input($_POST['contact'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $dob = $_POST['date_of_birth'] ?? '';
        $role = strtoupper(trim($_POST['role'] ?? 'USER'));
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $verificationStatus = $_POST['verification_status'] ?? 'pending';

        $errors = [];

        if ($firstName === '') {
            $errors[] = 'First name is required.';
        }

        if ($lastName === '') {
            $errors[] = 'Last name is required.';
        }

        if ($username === '' || strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if ($contact === '') {
            $errors[] = 'Contact number is required.';
        }

        if ($address === '') {
            $errors[] = 'Address is required.';
        }

        if ($dob === '') {
            $errors[] = 'Date of birth is required.';
        }

        if (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'Selected role is not allowed.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if (!empty($dob)) {
            try {
                $dobDate = new DateTime($dob);
                $age = (int) $dobDate->diff(new DateTime())->y;
                if ($age < 18) {
                    $errors[] = 'User must be at least 18 years old.';
                }
            } catch (Exception $e) {
                $errors[] = 'Invalid date of birth provided.';
            }
        }

        $usernameCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $usernameCheck->execute([$username]);
        if ($usernameCheck->fetchColumn() > 0) {
            $errors[] = 'Username is already taken.';
        }

        $emailCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $emailCheck->execute([$email]);
        if ($emailCheck->fetchColumn() > 0) {
            $errors[] = 'Email is already registered.';
        }

        if (!empty($errors)) {
            set_registration_flash('error', implode(' ', $errors));
            header("Location: Registratiom System.php");
            exit();
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
        $isVerified = $verificationStatus === 'verified' ? 1 : 0;
        $verificationCode = $isVerified ? null : generate_verification_code();
        $codeExpiry = $isVerified ? null : date('Y-m-d H:i:s', strtotime('+30 minutes'));

        $insertUser = $pdo->prepare("
            INSERT INTO users (
                first_name, middle_name, last_name,
                username, contact, address, date_of_birth,
                email, password, role, is_verified,
                verification_code, code_expiry
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $insertUser->execute([
            $firstName,
            $middleName,
            $lastName,
            $username,
            $contact,
            $address,
            $dob,
            $email,
            $hashedPassword,
            $role,
            $isVerified,
            $verificationCode,
            $codeExpiry
        ]);

        set_registration_flash('success', 'New user has been added successfully.');
        $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
        header("Location: Registratiom System.php");
        exit();
    }

    if ($action === 'update_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = strtoupper(trim($_POST['role'] ?? 'USER'));
        $status = $_POST['is_verified'] ?? '0';
        $isVerified = $status === '1' ? 1 : 0;

        if ($userId <= 0) {
            set_registration_flash('error', 'Unable to identify the selected user.');
            header("Location: Registratiom System.php");
            exit();
        }

        if (!in_array($role, $allowedRoles, true)) {
            set_registration_flash('error', 'Selected role is not allowed.');
            header("Location: Registratiom System.php");
            exit();
        }

        $userExists = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $userExists->execute([$userId]);
        if (!$userExists->fetchColumn()) {
            set_registration_flash('error', 'Selected user no longer exists.');
            header("Location: Registratiom System.php");
            exit();
        }

        if ($isVerified) {
            $update = $pdo->prepare("UPDATE users SET role = ?, is_verified = 1, verification_code = NULL, code_expiry = NULL, updated_at = NOW() WHERE id = ?");
            $update->execute([$role, $userId]);
        } else {
            $code = generate_verification_code();
            $expiry = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $update = $pdo->prepare("UPDATE users SET role = ?, is_verified = 0, verification_code = ?, code_expiry = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$role, $code, $expiry, $userId]);
        }

        set_registration_flash('success', 'User details have been updated.');
        $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
        header("Location: Registratiom System.php");
        exit();
    }

    if ($action === 'delete_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            set_registration_flash('error', 'Unable to identify the selected user.');
            header("Location: Registratiom System.php");
            exit();
        }

        if ($userId === $currentUserId) {
            set_registration_flash('error', 'You cannot delete your own account while logged in.');
            header("Location: Registratiom System.php");
            exit();
        }

        $delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $delete->execute([$userId]);

        set_registration_flash('success', 'User record has been deleted.');
        $_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
        header("Location: Registratiom System.php");
        exit();
    }

    set_registration_flash('error', 'Unsupported action requested.');
    header("Location: Registratiom System.php");
    exit();
}

$filters = [
    'status' => strtolower(trim($_GET['status'] ?? 'all')),
    'search' => trim($_GET['search'] ?? '')
];

$whereClauses = [];
$params = [];

switch ($filters['status']) {
    case 'verified':
        $whereClauses[] = 'is_verified = 1';
        break;
    case 'pending':
        $whereClauses[] = 'is_verified = 0';
        break;
    case 'admin':
    case 'employee':
    case 'user':
        $whereClauses[] = 'role = ?';
        $params[] = strtoupper($filters['status']);
        break;
    default:
        break;
}

if ($filters['search'] !== '') {
    $likeValue = '%' . $filters['search'] . '%';
    $whereClauses[] = '(first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR username LIKE ?)';
    $params = array_merge($params, [$likeValue, $likeValue, $likeValue, $likeValue, $likeValue]);
}

$userQuery = "SELECT id, first_name, middle_name, last_name, username, email, role, contact, address, is_verified, created_at
              FROM users";

if (!empty($whereClauses)) {
    $userQuery .= ' WHERE ' . implode(' AND ', $whereClauses);
}

$userQuery .= ' ORDER BY created_at DESC';

$userStmt = $pdo->prepare($userQuery);
$userStmt->execute($params);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [
    'total' => (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'verified' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 1")->fetchColumn(),
    'pending' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_verified = 0")->fetchColumn(),
];

$roleCounts = array_fill_keys($allowedRoles, 0);
$roleStmt = $pdo->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
foreach ($roleStmt->fetchAll(PDO::FETCH_ASSOC) as $roleRow) {
    $roleCounts[$roleRow['role']] = (int) $roleRow['total'];
}

$recentUsersStmt = $pdo->query("SELECT first_name, middle_name, last_name, role, is_verified, created_at FROM users ORDER BY created_at DESC LIMIT 6");
$recentUsers = $recentUsersStmt->fetchAll(PDO::FETCH_ASSOC);

try {
    $recentAttemptsStmt = $pdo->query("SELECT email, attempt_time, successful FROM registration_attempts ORDER BY attempt_time DESC LIMIT 6");
    $recentAttempts = $recentAttemptsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentAttempts = [];
}

$_SESSION['registration_csrf'] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION['registration_csrf'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration System - Community Policing and Surveillance</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <style>
        .content-wrapper {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding-bottom: 40px;
        }
        .card {
            background: var(--glass-bg, rgba(255,255,255,0.8));
            backdrop-filter: var(--glass-blur, blur(10px));
            border: 1px solid var(--glass-border, rgba(255,255,255,0.3));
            border-radius: 20px;
            padding: 24px;
            box-shadow: var(--glass-shadow, 0 20px 40px rgba(15,23,42,0.08));
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .stat-card {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .stat-label {
            font-size: 14px;
            color: var(--text-light);
            letter-spacing: 0.05em;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
        }
        .stat-subtext {
            font-size: 13px;
            color: var(--text-light);
        }
        .status-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }
        .status-filter select,
        .status-filter input[type="search"] {
            padding: 10px 14px;
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.08);
            min-width: 200px;
            background: rgba(255,255,255,0.7);
        }
        .status-filter button {
            padding: 10px 18px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: #fff;
            cursor: pointer;
        }
        .dual-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 16px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="password"],
        textarea,
        select {
            border-radius: 12px;
            border: 1px solid rgba(0,0,0,0.08);
            padding: 10px 12px;
            background: rgba(255,255,255,0.85);
            font-size: 14px;
        }
        textarea {
            min-height: 90px;
            resize: vertical;
        }
        .primary-button {
            margin-top: 16px;
            padding: 12px 24px;
            border-radius: 14px;
            border: none;
            background: linear-gradient(120deg, var(--primary-color), var(--secondary-color));
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
        }
        .user-table th,
        .user-table td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .user-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-light);
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-verified {
            background: #ecfdf5;
            color: #047857;
        }
        .badge-pending {
            background: #fff7ed;
            color: #c2410c;
        }
        .inline-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .inline-form select {
            min-width: 120px;
        }
        .icon-button {
            border: none;
            background: rgba(0,0,0,0.05);
            border-radius: 10px;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-color);
        }
        .flash-message {
            padding: 16px 20px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        .flash-success {
            background: #ecfdf5;
            color: #047857;
        }
        .flash-error {
            background: #fef2f2;
            color: #b91c1c;
        }
        .list-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 14px;
            padding: 12px 16px;
            background: rgba(255,255,255,0.65);
        }
        .list-item strong {
            display: block;
        }
        .list-item span {
            font-size: 13px;
            color: var(--text-light);
        }
        .role-chip {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .role-admin {
            background: rgba(239, 68, 68, 0.15);
            color: #b91c1c;
        }
        .role-employee {
            background: rgba(14, 165, 233, 0.15);
            color: #0369a1;
        }
        .role-user {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        @media (max-width: 960px) {
            .inline-form {
                flex-direction: column;
                align-items: stretch;
            }
            .status-filter input,
            .status-filter select {
                width: 100%;
                min-width: unset;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <aside class="sidebar">
            <div class="logo">
                <div class="logo-icon">
                    <img src="../img/cpas-logo.png" alt="Community Policing and Surveillance Logo" style="width: 40px; height: 45px;">
                </div>
                <span class="logo-text">CPAS Portal</span>
            </div>

            <div class="menu-section">
                <p class="menu-title">Admin Navigation</p>
                <div class="menu-items">
                    <a href="admin_dashboard.php" class="menu-item">
                        <div class="icon-box icon-bg-red">
                            <i class='bx bxs-dashboard icon-red'></i>
                        </div>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    <a href="Registratiom System.php" class="menu-item active">
                        <div class="icon-box icon-bg-blue">
                            <i class='bx bxs-user-plus icon-blue'></i>
                        </div>
                        <span class="font-medium">Registration System</span>
                    </a>
                    <a href="Summary Report.php" class="menu-item">
                        <div class="icon-box icon-bg-green">
                            <i class='bx bxs-report icon-green'></i>
                        </div>
                        <span class="font-medium">Summary Report</span>
                    </a>
                    <a href="Route Mapping.php" class="menu-item">
                        <div class="icon-box icon-bg-purple">
                            <i class='bx bxs-map icon-purple'></i>
                        </div>
                        <span class="font-medium">Route Mapping</span>
                    </a>
                    <a href="GPS Tracking.php" class="menu-item">
                        <div class="icon-box icon-bg-orange">
                            <i class='bx bx-satellite icon-orange'></i>
                        </div>
                        <span class="font-medium">GPS Tracking</span>
                    </a>
                </div>
            </div>

            <div class="menu-section">
                <p class="menu-title">Account</p>
                <div class="menu-items">
                    <a href="../includes/logout.php" class="menu-item">
                        <div class="icon-box icon-bg-teal">
                            <i class='bx bx-log-out icon-teal'></i>
                        </div>
                        <span class="font-medium">Sign out</span>
                    </a>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <div class="content-wrapper">
                <header class="header">
                    <div class="header-content">
                        <div>
                            <h1 style="font-size:24px;font-weight:700;">Registration System</h1>
                            <p style="color:var(--text-light);">Manage community access, verification, and onboarding.</p>
                        </div>
                        <div class="user-profile">
                            <div class="user-avatar">
                                <img src="../img/cpas-logo.png" alt="Avatar" style="width:40px;height:40px;border-radius:50%;">
                            </div>
                            <div class="user-info">
                                <div class="user-name"><?php echo htmlspecialchars($currentUserName); ?></div>
                                <div class="user-role"><?php echo htmlspecialchars($currentUserRole); ?></div>
                            </div>
                        </div>
                    </div>
                </header>

                <?php if ($flash): ?>
                    <div class="flash-message <?php echo $flash['type'] === 'success' ? 'flash-success' : 'flash-error'; ?>">
                        <i class='bx <?php echo $flash['type'] === 'success' ? 'bx-check-circle' : 'bx-error'; ?>'></i>
                        <span><?php echo htmlspecialchars($flash['message']); ?></span>
                    </div>
                <?php endif; ?>

                <section class="card">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <span class="stat-label">Total Registered</span>
                            <span class="stat-value"><?php echo number_format($stats['total']); ?></span>
                            <span class="stat-subtext">Across all roles</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">Verified Members</span>
                            <span class="stat-value"><?php echo number_format($stats['verified']); ?></span>
                            <span class="stat-subtext"><?php echo $stats['total'] > 0 ? round(($stats['verified'] / max(1, $stats['total'])) * 100) : 0; ?>% of total</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">Pending Approval</span>
                            <span class="stat-value"><?php echo number_format($stats['pending']); ?></span>
                            <span class="stat-subtext">Awaiting verification</span>
                        </div>
                        <div class="stat-card">
                            <span class="stat-label">Role Breakdown</span>
                            <span class="stat-value"><?php echo number_format($roleCounts['ADMIN'] ?? 0); ?> / <?php echo number_format($roleCounts['EMPLOYEE'] ?? 0); ?> / <?php echo number_format($roleCounts['USER'] ?? 0); ?></span>
                            <span class="stat-subtext">Admin • Employee • User</span>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <form class="status-filter" method="get">
                        <div>
                            <label>
                                Status / Role
                                <select name="status">
                                    <option value="all" <?php echo $filters['status'] === 'all' ? 'selected' : ''; ?>>All records</option>
                                    <option value="verified" <?php echo $filters['status'] === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                    <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="admin" <?php echo $filters['status'] === 'admin' ? 'selected' : ''; ?>>Admins</option>
                                    <option value="employee" <?php echo $filters['status'] === 'employee' ? 'selected' : ''; ?>>Employees</option>
                                    <option value="user" <?php echo $filters['status'] === 'user' ? 'selected' : ''; ?>>Community Users</option>
                                </select>
                            </label>
                        </div>
                        <div style="flex:1;min-width:220px;">
                            <label>
                                Keyword search
                                <input type="search" name="search" placeholder="Search name, username, email..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                            </label>
                        </div>
                        <div>
                            <button type="submit"><i class='bx bx-filter-alt'></i>&nbsp;Apply filters</button>
                        </div>
                        <div>
                            <a href="Registratiom System.php" class="icon-button" title="Clear filters">
                                <i class='bx bx-reset'></i>
                            </a>
                        </div>
                    </form>
                </section>

                <section class="dual-grid">
                    <div class="card">
                        <h2 style="font-size:18px;margin-bottom:16px;">Add community account</h2>
                        <form method="post">
                            <input type="hidden" name="action" value="create_user">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <div class="form-grid">
                                <label>First name
                                    <input type="text" name="first_name" required>
                                </label>
                                <label>Middle name
                                    <input type="text" name="middle_name">
                                </label>
                                <label>Last name
                                    <input type="text" name="last_name" required>
                                </label>
                                <label>Username
                                    <input type="text" name="username" required>
                                </label>
                                <label>Email address
                                    <input type="email" name="email" required>
                                </label>
                                <label>Contact number
                                    <input type="text" name="contact" required>
                                </label>
                                <label>Date of birth
                                    <input type="date" name="date_of_birth" required>
                                </label>
                                <label>Role
                                    <select name="role" required>
                                        <?php foreach ($allowedRoles as $role): ?>
                                            <option value="<?php echo $role; ?>"><?php echo ucfirst(strtolower($role)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Password
                                    <input type="password" name="password" required>
                                </label>
                                <label>Confirm password
                                    <input type="password" name="confirm_password" required>
                                </label>
                                <label>Verification status
                                    <select name="verification_status">
                                        <option value="pending">Pending</option>
                                        <option value="verified">Verified</option>
                                    </select>
                                </label>
                            </div>
                            <label>Address / Notes
                                <textarea name="address" required></textarea>
                            </label>
                            <button type="submit" class="primary-button"><i class='bx bx-save'></i>&nbsp;Save new record</button>
                        </form>
                    </div>

                    <div class="card">
                        <h2 style="font-size:18px;margin-bottom:16px;">Recent onboarding</h2>
                        <div class="list-group">
                            <?php if (empty($recentUsers)): ?>
                                <p style="color:var(--text-light);">No registered users yet.</p>
                            <?php else: ?>
                                <?php foreach ($recentUsers as $recent): ?>
                                    <div class="list-item">
                                        <div>
                                            <strong><?php echo htmlspecialchars(get_full_name($recent)); ?></strong>
                                            <span><?php echo htmlspecialchars(date('M d, Y g:i A', strtotime($recent['created_at']))); ?></span>
                                        </div>
                                        <div style="text-align:right;">
                                            <div class="role-chip role-<?php echo strtolower($recent['role']); ?>"><?php echo htmlspecialchars($recent['role']); ?></div>
                                            <div class="badge <?php echo $recent['is_verified'] ? 'badge-verified' : 'badge-pending'; ?>" style="margin-top:6px;">
                                                <?php echo $recent['is_verified'] ? 'Verified' : 'Pending'; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <h2 style="font-size:18px;margin:24px 0 16px;">Registration attempts</h2>
                        <div class="list-group">
                            <?php if (empty($recentAttempts)): ?>
                                <p style="color:var(--text-light);">No recorded attempts yet.</p>
                            <?php else: ?>
                                <?php foreach ($recentAttempts as $attempt): ?>
                                    <div class="list-item">
                                        <div>
                                            <strong><?php echo htmlspecialchars($attempt['email']); ?></strong>
                                            <span><?php echo htmlspecialchars(date('M d, Y g:i A', strtotime($attempt['attempt_time']))); ?></span>
                                        </div>
                                        <div class="badge <?php echo $attempt['successful'] ? 'badge-verified' : 'badge-pending'; ?>">
                                            <?php echo $attempt['successful'] ? 'Successful' : 'Blocked'; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="card">
                    <h2 style="font-size:18px;margin-bottom:16px;">Registry overview</h2>
                    <div style="overflow-x:auto;">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username / Email</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th style="min-width:240px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center;color:var(--text-light);padding:24px;">
                                            No records match your filters yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight:600;"><?php echo htmlspecialchars(get_full_name($user)); ?></div>
                                                <div class="role-chip role-<?php echo strtolower($user['role']); ?>"><?php echo htmlspecialchars($user['role']); ?></div>
                                            </td>
                                            <td>
                                                <div><?php echo htmlspecialchars($user['username']); ?></div>
                                                <div style="font-size:13px;color:var(--text-light);"><?php echo htmlspecialchars($user['email']); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['contact']); ?></td>
                                            <td>
                                                <div class="badge <?php echo $user['is_verified'] ? 'badge-verified' : 'badge-pending'; ?>">
                                                    <?php echo $user['is_verified'] ? 'Verified' : 'Pending'; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($user['created_at']))); ?></td>
                                            <td>
                                                <form method="post" class="inline-form" style="margin-bottom:6px;">
                                                    <input type="hidden" name="action" value="update_user">
                                                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <select name="role">
                                                        <?php foreach ($allowedRoles as $roleOpt): ?>
                                                            <option value="<?php echo $roleOpt; ?>" <?php echo $user['role'] === $roleOpt ? 'selected' : ''; ?>>
                                                                <?php echo ucfirst(strtolower($roleOpt)); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <select name="is_verified">
                                                        <option value="1" <?php echo $user['is_verified'] ? 'selected' : ''; ?>>Verified</option>
                                                        <option value="0" <?php echo !$user['is_verified'] ? 'selected' : ''; ?>>Pending</option>
                                                    </select>
                                                    <button class="icon-button" type="submit" title="Save changes"><i class='bx bx-save'></i></button>
                                                </form>
                                                <?php if ((int) $user['id'] !== $currentUserId): ?>
                                                    <form method="post" onsubmit="return confirm('Delete this user record? This cannot be undone.');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                        <button class="icon-button" type="submit" title="Delete record" style="background:rgba(239,68,68,0.12);color:#b91c1c;">
                                                            <i class='bx bx-trash'></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>

