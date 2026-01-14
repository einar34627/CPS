<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

// Fetch GPS statistics
try {
    $stats = [];
    
    // Total devices
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_units WHERE is_active = 1");
    $stats['total_devices'] = $stmt->fetch()['count'];
    
    // Active devices
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_units WHERE is_active = 1 AND status IN ('On Patrol', 'Responding')");
    $stats['active_devices'] = $stmt->fetch()['count'];
    
    // Devices needing assistance
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_units WHERE is_active = 1 AND status = 'Needs Assistance'");
    $stats['alert_devices'] = $stmt->fetch()['count'];
    
    // Total distance today
    $stmt = $pdo->query("SELECT SUM(distance_today) as total FROM gps_units WHERE is_active = 1");
    $stats['total_distance'] = $stmt->fetch()['total'] ?? 0;
    
    // Recent updates (last hour)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_units WHERE is_active = 1 AND last_ping >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $stats['recent_updates'] = $stmt->fetch()['count'];
    
    // Get latest devices
    $stmt = $pdo->query("
        SELECT unit_id, callsign, status, last_ping, battery
        FROM gps_units 
        WHERE is_active = 1
        ORDER BY last_ping DESC
        LIMIT 5
    ");
    $latestDevices = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $stats = [
        'total_devices' => 0,
        'active_devices' => 0,
        'alert_devices' => 0,
        'total_distance' => 0,
        'recent_updates' => 0
    ];
    $latestDevices = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Dashboard | CPAS</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <style>
        :root {
            --bg: #f5f6fb;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #2563eb;
            --warning: #f59e0b;
            --success: #16a34a;
            --danger: #dc2626;
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px clamp(16px, 4vw, 48px);
        }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: clamp(28px, 4vw, 40px);
            margin: 0;
            font-weight: 700;
        }

        .page-subtitle {
            color: var(--muted);
            margin-top: 8px;
            font-size: 16px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ghost-button,
        .primary-button {
            border-radius: 999px;
            padding: 10px 18px;
            border: 1px solid var(--border);
            background: transparent;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .primary-button {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .primary-button:hover {
            filter: brightness(0.95);
        }

        .ghost-button:hover {
            background: rgba(15, 23, 42, 0.05);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 14px;
            font-weight: 500;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue {
            background: rgba(37, 99, 235, 0.15);
            color: var(--primary);
        }

        .stat-icon.green {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
        }

        .stat-icon.red {
            background: rgba(220, 38, 38, 0.15);
            color: var(--danger);
        }

        .stat-icon.orange {
            background: rgba(245, 158, 11, 0.15);
            color: var(--warning);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            margin: 8px 0;
        }

        .stat-change {
            font-size: 13px;
            color: var(--muted);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .feature-card {
            background: var(--card);
            border-radius: 20px;
            padding: 28px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.1);
            border-color: var(--primary);
        }

        .feature-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 20px;
        }

        .feature-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 8px 0;
        }

        .feature-description {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .recent-activity {
            background: var(--card);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 20px 0;
        }

        .device-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .device-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: var(--bg);
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .device-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .device-name {
            font-weight: 600;
            font-size: 14px;
        }

        .device-meta {
            font-size: 12px;
            color: var(--muted);
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-patrol {
            background: rgba(59, 130, 246, 0.15);
            color: #1d4ed8;
        }

        .status-responding {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
        }

        .status-stationary {
            background: rgba(148, 163, 184, 0.25);
            color: #475569;
        }

        .status-assist {
            background: rgba(248, 113, 113, 0.15);
            color: var(--danger);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 48px;
            opacity: 0.5;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">GPS Tracking System</h1>
                <p class="page-subtitle">
                    Comprehensive GPS tracking and device management dashboard
                </p>
            </div>
            <div class="header-actions">
                <a class="ghost-button" href="admin_dashboard.php">
                    <i class='bx bxs-dashboard'></i>
                    Main Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Devices</span>
                    <div class="stat-icon blue">
                        <i class='bx bx-devices'></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['total_devices']; ?></div>
                <div class="stat-change">Registered GPS units</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Active Units</span>
                    <div class="stat-icon green">
                        <i class='bx bx-walk'></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['active_devices']; ?></div>
                <div class="stat-change">On patrol or responding</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Alerts</span>
                    <div class="stat-icon red">
                        <i class='bx bx-error'></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['alert_devices']; ?></div>
                <div class="stat-change">Needs assistance</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Distance Today</span>
                    <div class="stat-icon orange">
                        <i class='bx bx-map'></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_distance'], 1); ?> km</div>
                <div class="stat-change">Total coverage</div>
            </div>
        </div>

        <!-- Features -->
        <div class="features-grid">
            <a href="GPS Tracking.php" class="feature-card">
                <div class="feature-icon">
                    <i class='bx bx-map-alt'></i>
                </div>
                <h3 class="feature-title">Real-time GPS Tracking</h3>
                <p class="feature-description">
                    Monitor all GPS devices on an interactive map with real-time updates. View unit locations, status, and movement patterns.
                </p>
            </a>

            <a href="GPS Device Management.php" class="feature-card">
                <div class="feature-icon">
                    <i class='bx bx-cog'></i>
                </div>
                <h3 class="feature-title">Device Management</h3>
                <p class="feature-description">
                    Add, edit, and manage GPS tracking devices. Configure device settings, assignments, and view detailed information.
                </p>
            </a>

            <a href="GPS Device Simulator.php" class="feature-card">
                <div class="feature-icon">
                    <i class='bx bx-play-circle'></i>
                </div>
                <h3 class="feature-title">Device Simulator</h3>
                <p class="feature-description">
                    Create test GPS devices and simulate GPS updates for testing and development purposes.
                </p>
            </a>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <h3 class="section-title">Recent Device Activity</h3>
            <?php if (empty($latestDevices)): ?>
                <div class="empty-state">
                    <i class='bx bx-devices'></i>
                    <p>No GPS devices registered yet</p>
                    <p style="font-size: 12px; margin-top: 8px;">Create a device to start tracking</p>
                </div>
            <?php else: ?>
                <div class="device-list">
                    <?php foreach ($latestDevices as $device): ?>
                        <div class="device-item">
                            <div class="device-info">
                                <div class="device-name"><?php echo htmlspecialchars($device['callsign']); ?></div>
                                <div class="device-meta">
                                    <?php echo htmlspecialchars($device['unit_id']); ?> • 
                                    Battery: <?php echo $device['battery']; ?>% • 
                                    Updated: <?php echo date('H:i', strtotime($device['last_ping'])); ?>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $device['status'])); ?>">
                                <?php echo htmlspecialchars($device['status']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

