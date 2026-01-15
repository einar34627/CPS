<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$range = isset($_GET['range']) ? strtolower($_GET['range']) : '24h';
$range = in_array($range, ['24h','7d','30d']) ? $range : '24h';
$rangeStartExpr = $range === '7d' ? 'DATE_SUB(NOW(), INTERVAL 7 DAY)' : ($range === '30d' ? 'DATE_SUB(NOW(), INTERVAL 30 DAY)' : 'DATE_SUB(NOW(), INTERVAL 1 DAY)');
$rangeLabel = $range === '7d' ? 'Last 7 days' : ($range === '30d' ? 'Last 30 days' : 'Last 24 hours');
$simple = isset($_GET['simple']) ? ($_GET['simple'] === '1') : true;
$fullName = 'Administrator';
$roleLabel = 'ADMIN';

try {
    $userStmt = $pdo->prepare("SELECT first_name, middle_name, last_name, role FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    if ($user = $userStmt->fetch()) {
        $parts = [
            htmlspecialchars($user['first_name'] ?? ''),
            htmlspecialchars($user['middle_name'] ?? ''),
            htmlspecialchars($user['last_name'] ?? '')
        ];
        $fullName = trim(implode(' ', array_filter($parts)));
        $roleLabel = htmlspecialchars($user['role'] ?? 'ADMIN');
    }
    $userStmt = null;
} catch (Exception $e) {
    // Leave default labels on failure; page continues with static fallbacks.
}

$totalUsers = 0;
$verifiedUsers = 0;
$pendingVerifications = 0;
$successfulLogins24h = 0;
$registrationAttempts24h = 0;
$totalLoginAttempts = 0;

$roleStats = [
    'ADMIN' => 0,
    'EMPLOYEE' => 0,
    'USER' => 0
];

$recentUsers = [];
$recentLogins = [];
$registrationPulse = [];
$verificationQueue = [];

try {
    $statsStmt = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(is_verified),0) AS verified FROM users");
    if ($row = $statsStmt->fetch()) {
        $totalUsers = (int) ($row['total'] ?? 0);
        $verifiedUsers = (int) ($row['verified'] ?? 0);
    }
    $statsStmt = null;

    $roleStmt = $pdo->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
    while ($roleRow = $roleStmt->fetch()) {
        $role = strtoupper($roleRow['role'] ?? '');
        if (isset($roleStats[$role])) {
            $roleStats[$role] = (int) $roleRow['total'];
        } else {
            $roleStats[$role] = (int) $roleRow['total'];
        }
    }
    $roleStmt = null;

    $recentStmt = $pdo->query("SELECT first_name, middle_name, last_name, email, role, is_verified, created_at FROM users ORDER BY created_at DESC LIMIT 6");
    $recentUsers = $recentStmt ? $recentStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $recentStmt = null;
} catch (Exception $e) {
    // Continue with defaults if queries fail
}

try {
    $loginStmt = $pdo->query("SELECT email, attempt_time, successful FROM login_attempts WHERE attempt_time >= $rangeStartExpr ORDER BY attempt_time DESC LIMIT 12");
    $recentLogins = $loginStmt ? $loginStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $loginStmt = null;

    $successfulStmt = $pdo->query("SELECT COUNT(*) FROM login_attempts WHERE successful = 1 AND attempt_time >= $rangeStartExpr");
    $successfulLogins24h = $successfulStmt ? (int) $successfulStmt->fetchColumn() : 0;
    $successfulStmt = null;

    $totalLoginStmt = $pdo->query("SELECT COUNT(*) FROM login_attempts");
    $totalLoginAttempts = $totalLoginStmt ? (int) $totalLoginStmt->fetchColumn() : 0;
    $totalLoginStmt = null;
} catch (Exception $e) {
    // Leave login defaults
}

try {
    $regStmt = $pdo->query("SELECT email, attempt_time, successful FROM registration_attempts WHERE attempt_time >= $rangeStartExpr ORDER BY attempt_time DESC LIMIT 12");
    $registrationPulse = $regStmt ? $regStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $regStmt = null;

    $regCountStmt = $pdo->query("SELECT COUNT(*) FROM registration_attempts WHERE attempt_time >= $rangeStartExpr");
    $registrationAttempts24h = $regCountStmt ? (int) $regCountStmt->fetchColumn() : 0;
    $regCountStmt = null;
} catch (Exception $e) {
    // Leave registration defaults
}

try {
    $verificationStmt = $pdo->query("SELECT email, code, expiry, created_at FROM verification_codes ORDER BY created_at DESC LIMIT 5");
    $verificationQueue = $verificationStmt ? $verificationStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $verificationStmt = null;
} catch (Exception $e) {
    // Leave verification queue empty
}

$pendingVerifications = max($totalUsers - $verifiedUsers, count($verificationQueue));

$activationRate = $totalUsers > 0 ? round(($verifiedUsers / $totalUsers) * 100, 1) : 0;
$pendingRate = $totalUsers > 0 ? round(($pendingVerifications / $totalUsers) * 100, 1) : 0;
$avgAttemptsPerUser = $totalUsers > 0 ? round($totalLoginAttempts / $totalUsers, 1) : 0;

function formatDateTime(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('M d, Y g:i A', $timestamp) : '—';
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="summary-report.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Generated At', date('Y-m-d H:i:s')]);
    fputcsv($out, ['Range', $rangeLabel]);
    fputcsv($out, []);
    fputcsv($out, ['Metric', 'Value']);
    fputcsv($out, ['Registered Residents', $totalUsers]);
    fputcsv($out, ['Verified Accounts', $verifiedUsers]);
    fputcsv($out, ['Pending Verifications', $pendingVerifications]);
    fputcsv($out, ['Activation Rate (%)', $activationRate]);
    fputcsv($out, ['Pending Rate (%)', $pendingRate]);
    fputcsv($out, ['Successful Logins (24h)', $successfulLogins24h]);
    fputcsv($out, ['Avg Login Attempts/User', $avgAttemptsPerUser]);
    fputcsv($out, []);
    fputcsv($out, ['Role', 'Count']);
    foreach ($roleStats as $role => $count) {
        fputcsv($out, [$role, $count]);
    }
    fclose($out);
    exit;
}

function formatName(array $user): string
{
    $parts = [
        $user['first_name'] ?? '',
        $user['middle_name'] ?? '',
        $user['last_name'] ?? ''
    ];
    $name = trim(implode(' ', array_filter($parts)));
    return $name !== '' ? htmlspecialchars($name) : '—';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary Reports | CPAS</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f6fb;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #2563eb;
            --accent: #f97316;
            --success: #16a34a;
            --danger: #dc2626;
            --shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .page {
            max-width: 1320px;
            margin: 0 auto;
            padding: 32px clamp(16px, 4vw, 48px) 64px;
        }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: clamp(26px, 4vw, 40px);
            margin: 0;
        }

        .page-header p {
            margin: 8px 0 0;
            color: var(--muted);
            max-width: 640px;
        }

        .controls {
            display: flex;
            align-items: flex-end;
            gap: 12px;
        }

        .range-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .range-form select {
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--card);
            font-family: inherit;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.12);
            color: var(--primary);
            font-weight: 600;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px;
        }

        .card {
            background: var(--card);
            border-radius: 24px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .card h3 {
            margin: 0 0 12px;
            font-size: 15px;
            color: var(--muted);
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .simple .page-header h1 { font-size: 28px; }
        .simple .card { box-shadow: none; border-radius: 12px; padding: 16px; }
        .simple .stat-value { font-size: 28px; }
        .simple .grid { gap: 12px; }
        .simple .section-title { font-size: 18px; }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
        }

        .stat-note {
            color: var(--muted);
            font-size: 13px;
            margin-top: 6px;
        }

        .trend-positive {
            color: var(--success);
            font-weight: 600;
        }

        .trend-negative {
            color: var(--danger);
            font-weight: 600;
        }

        .section-title {
            font-size: 20px;
            margin: 0 0 16px;
            font-weight: 600;
        }

        .section-note {
            margin-top: -8px;
            margin-bottom: 10px;
            color: var(--muted);
            font-size: 13px;
        }

        .table {
            width: 100%;
            border-spacing: 0;
        }

        .table th {
            text-align: left;
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding-bottom: 12px;
        }

        .table td {
            padding: 14px 0;
            border-top: 1px solid var(--border);
            font-size: 15px;
        }

        .table tr:first-of-type td {
            border-top: none;
        }

        .role-pill {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(15, 23, 42, 0.05);
        }

        .role-pill[data-role="ADMIN"] { background: rgba(220, 38, 38, 0.1); color: var(--danger); }
        .role-pill[data-role="EMPLOYEE"] { background: rgba(249, 115, 22, 0.12); color: var(--accent); }
        .role-pill[data-role="USER"] { background: rgba(37, 99, 235, 0.15); color: var(--primary); }

        .status-chip {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-chip[data-state="verified"] {
            background: rgba(22, 163, 74, 0.15);
            color: var(--success);
        }

        .status-chip[data-state="pending"] {
            background: rgba(249, 115, 22, 0.12);
            color: var(--accent);
        }

        .text-muted {
            color: var(--muted);
        }

        .split {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
            margin-top: 32px;
        }

        .pill-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .bar-row {
            display: grid;
            grid-template-columns: 160px 1fr 64px;
            gap: 12px;
            align-items: center;
        }

        .bar-track {
            height: 12px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .bar-fill {
            height: 100%;
            background: var(--primary);
        }

        .pill-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 18px;
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.03);
            font-weight: 600;
        }

        .pill-item span:last-child {
            font-size: 18px;
        }

        .log-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .log-item {
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .log-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .log-item strong {
            display: block;
            margin-bottom: 4px;
        }

        .insights {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-top: 32px;
        }

        .insight {
            background: rgba(37, 99, 235, 0.08);
            border: 1px dashed rgba(37, 99, 235, 0.4);
            border-radius: 18px;
            padding: 18px;
        }

        .insight p {
            margin: 0;
            color: var(--text);
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 48px;
            padding-top: 32px;
            border-top: 2px solid var(--border);
            justify-content: center;
            position: relative;
            z-index: 1;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            position: relative;
            z-index: 1;
            pointer-events: auto;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-secondary {
            background: var(--card);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .btn-secondary:hover {
            background: rgba(15, 23, 42, 0.05);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #15803d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-compact {
            padding: 8px 14px;
            font-size: 13px;
            border-radius: 999px;
        }

        @media (max-width: 640px) {
            .page {
                padding: 24px 18px 48px;
            }

            .card {
                border-radius: 18px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <main class="page<?php echo $simple ? ' simple' : ''; ?>">
        <header class="page-header">
            <div>
                <h1>Summary Reports</h1>
                <p>
                    High-level visibility into account activity, verification status, and security signals
                    gathered from the registration and login pipeline.
                </p>
                <p class="text-muted">As of <?php echo date('M d, Y g:i A'); ?></p>
            </div>
            <div class="controls">
                <div class="badge">
                    <span><?php echo htmlspecialchars($fullName); ?></span>
                    <span>&middot;</span>
                    <span><?php echo htmlspecialchars($roleLabel); ?></span>
                </div>
                <a href="Registratiom System.php" class="btn btn-secondary btn-compact">Manage Users</a>
                <form class="range-form" method="get">
                    <select name="range">
                        <option value="24h" <?php echo $range === '24h' ? 'selected' : ''; ?>>Last 24 hours</option>
                        <option value="7d" <?php echo $range === '7d' ? 'selected' : ''; ?>>Last 7 days</option>
                        <option value="30d" <?php echo $range === '30d' ? 'selected' : ''; ?>>Last 30 days</option>
                    </select>
                    <input type="hidden" name="simple" value="<?php echo $simple ? '1' : '0'; ?>">
                    <button class="btn btn-secondary" type="submit">Apply</button>
                    <button class="btn btn-secondary" type="submit" name="export" value="csv">Export CSV</button>
                    <?php if ($simple): ?>
                        <a class="btn btn-secondary" href="?range=<?php echo urlencode($range); ?>&simple=0">Detailed View</a>
                    <?php else: ?>
                        <a class="btn btn-secondary" href="?range=<?php echo urlencode($range); ?>&simple=1">Simple View</a>
                    <?php endif; ?>
                </form>
            </div>
        </header>

        <section class="grid">
            <article class="card">
                <h3>Registered Residents</h3>
                <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                <div class="stat-note"><?php echo $registrationAttempts24h; ?> new registration attempt(s) in <?php echo strtolower($rangeLabel); ?></div>
            </article>

            <article class="card">
                <h3>Verified Accounts</h3>
                <div class="stat-value"><?php echo number_format($verifiedUsers); ?></div>
                <div class="stat-note">
                    <span class="trend-positive"><?php echo $activationRate; ?>%</span> activation rate across all registered users
                </div>
            </article>

            <article class="card">
                <h3>Pending Verifications</h3>
                <div class="stat-value"><?php echo number_format($pendingVerifications); ?></div>
                <div class="stat-note">
                    <span class="trend-negative"><?php echo $pendingRate; ?>%</span> of the user base still awaiting confirmation
                </div>
            </article>

            <article class="card">
                <h3>Successful Logins</h3>
                <div class="stat-value"><?php echo number_format($successfulLogins24h); ?></div>
                <div class="stat-note">Range: <?php echo htmlspecialchars($rangeLabel); ?> • <?php echo $avgAttemptsPerUser; ?> average login attempts per user</div>
            </article>
        </section>

        <?php if (!$simple): ?>
        <section class="split">
            <article class="card">
                <h2 class="section-title">Role Distribution</h2>
                <div class="pill-list">
                    <?php $roleTotal = array_sum($roleStats); ?>
                    <?php foreach ($roleStats as $role => $count): ?>
                        <?php $pct = $roleTotal > 0 ? round(($count / $roleTotal) * 100) : 0; ?>
                        <div class="bar-row">
                            <div><?php echo htmlspecialchars(ucfirst(strtolower($role))); ?></div>
                            <div class="bar-track"><div class="bar-fill" style="width: <?php echo $pct; ?>%"></div></div>
                            <div><?php echo number_format($count); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card">
                <h2 class="section-title">Verification Queue</h2>
                <?php if (empty($verificationQueue)): ?>
                    <p class="text-muted">No verification codes have been requested recently.</p>
                <?php else: ?>
                    <div class="log-list">
                        <?php foreach ($verificationQueue as $entry): ?>
                            <div class="log-item">
                                <strong><?php echo htmlspecialchars($entry['email'] ?? 'Unknown'); ?></strong>
                                <div class="text-muted">Code <?php echo htmlspecialchars($entry['code'] ?? ''); ?> issued <?php echo formatDateTime($entry['created_at'] ?? null); ?></div>
                                <div class="text-muted">Expires <?php echo formatDateTime($entry['expiry'] ?? null); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
        <?php endif; ?>

        <section class="card" style="margin-top: 32px;">
            <h2 class="section-title">Latest Registrations</h2>
            <?php if (empty($recentUsers)): ?>
                <p class="text-muted">No user registrations found in the system yet.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $account): ?>
                            <tr>
                                <td><?php echo formatName($account); ?></td>
                                <td><?php echo htmlspecialchars($account['email'] ?? ''); ?></td>
                                <td>
                                    <span class="role-pill" data-role="<?php echo htmlspecialchars(strtoupper($account['role'] ?? 'USER')); ?>">
                                        <?php echo htmlspecialchars($account['role'] ?? 'USER'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($account['is_verified'])): ?>
                                        <span class="status-chip" data-state="verified">Verified</span>
                                    <?php else: ?>
                                        <span class="status-chip" data-state="pending">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDateTime($account['created_at'] ?? null); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <?php if (!$simple): ?>
        <section class="split">
            <article class="card">
                <h2 class="section-title">Login Activity</h2>
                <div class="section-note">Range: <?php echo htmlspecialchars($rangeLabel); ?></div>
                <?php if (empty($recentLogins)): ?>
                    <p class="text-muted">No login attempts recorded.</p>
                <?php else: ?>
                    <div class="log-list">
                        <?php foreach ($recentLogins as $attempt): ?>
                            <div class="log-item">
                                <strong><?php echo htmlspecialchars($attempt['email'] ?? 'Unknown email'); ?></strong>
                                <div class="text-muted"><?php echo formatDateTime($attempt['attempt_time'] ?? null); ?></div>
                                <div class="<?php echo !empty($attempt['successful']) ? 'trend-positive' : 'trend-negative'; ?>">
                                    <?php echo !empty($attempt['successful']) ? 'Successful login' : 'Failed attempt'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>

            <article class="card">
                <h2 class="section-title">Registration Attempts</h2>
                <div class="section-note">Range: <?php echo htmlspecialchars($rangeLabel); ?></div>
                <?php if (empty($registrationPulse)): ?>
                    <p class="text-muted">No registration attempts captured.</p>
                <?php else: ?>
                    <div class="log-list">
                        <?php foreach ($registrationPulse as $attempt): ?>
                            <div class="log-item">
                                <strong><?php echo htmlspecialchars($attempt['email'] ?? 'Unknown email'); ?></strong>
                                <div class="text-muted"><?php echo formatDateTime($attempt['attempt_time'] ?? null); ?></div>
                                <div class="<?php echo !empty($attempt['successful']) ? 'trend-positive' : 'trend-negative'; ?>">
                                    <?php echo !empty($attempt['successful']) ? 'Completed registration' : 'Incomplete attempt'; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </section>
        <?php endif; ?>

        <?php if (!$simple): ?>
        <section class="insights">
            <div class="insight">
                <?php if ($pendingVerifications > 0): ?>
                    <p><strong>Activation Guidance:</strong> <?php echo number_format($pendingVerifications); ?> resident(s) awaiting verification. Target outreach to raise activation from <?php echo $activationRate; ?>%.</p>
                <?php else: ?>
                    <p><strong>Activation Complete:</strong> All residents verified. Activation rate at <?php echo $activationRate; ?>%.</p>
                <?php endif; ?>
            </div>
            <div class="insight">
                <?php if ($avgAttemptsPerUser > 3): ?>
                    <p><strong>Security Watch:</strong> Average login retries at <?php echo $avgAttemptsPerUser; ?> per user. Investigate spikes above 3 attempts.</p>
                <?php else: ?>
                    <p><strong>Security Watch:</strong> Login patterns normal at <?php echo $avgAttemptsPerUser; ?> average attempts per user.</p>
                <?php endif; ?>
            </div>
            <div class="insight">
                <?php if ($registrationAttempts24h > 0): ?>
                    <p><strong>Onboarding Pulse:</strong> <?php echo $registrationAttempts24h; ?> registration attempt(s) detected <?php echo strtolower($rangeLabel); ?>.</p>
                <?php else: ?>
                    <p><strong>Onboarding Pulse:</strong> No registration attempts <?php echo strtolower($rangeLabel); ?>.</p>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!$simple): ?>
        <div class="action-buttons">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <path d="M6 14h12v8H6z"/>
                </svg>
                Print Report
            </button>
            <button type="button" class="btn btn-success" onclick="location.reload()">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                </svg>
                Refresh Data
            </button>
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                Back to Dashboard
            </a>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>

