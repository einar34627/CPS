<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

// Fetch comprehensive GPS statistics
try {
    // Total devices
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_units WHERE is_active = 1");
    $totalDevices = $stmt->fetch()['count'];
    
    // Active devices
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_units WHERE is_active = 1 AND status IN ('On Patrol', 'Responding')");
    $activeDevices = $stmt->fetch()['count'];
    
    // Devices needing assistance
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_units WHERE is_active = 1 AND status = 'Needs Assistance'");
    $alertDevices = $stmt->fetch()['count'];
    
    // Total distance today
    $stmt = $pdo->query("SELECT SUM(distance_today) as total FROM gps_units WHERE is_active = 1");
    $totalDistance = $stmt->fetch()['total'] ?? 0;
    
    // Recent updates (last hour)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_units WHERE is_active = 1 AND last_ping >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    $recentUpdates = $stmt->fetch()['count'];
    
    // Low battery devices
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_units WHERE is_active = 1 AND battery < 20");
    $lowBattery = $stmt->fetch()['count'];
    
    // Get all active devices
    $stmt = $pdo->query("
        SELECT 
            unit_id,
            callsign,
            assignment,
            latitude,
            longitude,
            status,
            speed,
            battery,
            distance_today,
            last_ping,
            TIMESTAMPDIFF(MINUTE, last_ping, NOW()) as minutes_ago
        FROM gps_units 
        WHERE is_active = 1
        ORDER BY 
            CASE status
                WHEN 'Needs Assistance' THEN 1
                WHEN 'Responding' THEN 2
                WHEN 'On Patrol' THEN 3
                ELSE 4
            END,
            last_ping DESC
    ");
    $allDevices = $stmt->fetchAll();
    
    // Get recent GPS history (last 24 hours)
    $stmt = $pdo->query("
        SELECT 
            gh.unit_id,
            u.callsign,
            COUNT(*) as point_count,
            MAX(gh.recorded_at) as last_record
        FROM gps_history gh
        JOIN gps_units u ON gh.unit_id = u.unit_id
        WHERE gh.recorded_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY gh.unit_id, u.callsign
        ORDER BY last_record DESC
        LIMIT 10
    ");
    $recentHistory = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $totalDevices = 0;
    $activeDevices = 0;
    $alertDevices = 0;
    $totalDistance = 0;
    $recentUpdates = 0;
    $lowBattery = 0;
    $allDevices = [];
    $recentHistory = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS System | CPAS</title>
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
            max-width: 1600px;
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
            font-size: clamp(32px, 5vw, 48px);
            margin: 0;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            color: var(--muted);
            margin-top: 8px;
            font-size: 18px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .ghost-button,
        .primary-button {
            border-radius: 999px;
            padding: 12px 20px;
            border: 1px solid var(--border);
            background: transparent;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .primary-button {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .primary-button:hover {
            filter: brightness(0.95);
            transform: translateY(-1px);
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
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary);
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
            font-size: 40px;
            font-weight: 700;
            margin: 8px 0;
        }

        .stat-change {
            font-size: 13px;
            color: var(--muted);
        }

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        @media (max-width: 1200px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: var(--card);
            border-radius: 24px;
            padding: 28px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .device-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 600px;
            overflow-y: auto;
        }

        .device-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: var(--bg);
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: all 0.2s ease;
        }

        .device-item:hover {
            background: rgba(37, 99, 235, 0.05);
            border-color: var(--primary);
            cursor: pointer;
        }

        .device-info {
            flex: 1;
        }

        .device-name {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .device-meta {
            font-size: 12px;
            color: var(--muted);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .device-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
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
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .battery-indicator {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
        }

        .battery-low {
            color: var(--danger);
        }

        .battery-medium {
            color: var(--warning);
        }

        .battery-high {
            color: var(--success);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
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

        .alert-banner {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid var(--danger);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-banner.hidden {
            display: none;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: 16px;
        }

        .time-ago {
            font-size: 11px;
            color: var(--muted);
        }

        .time-ago.warning {
            color: var(--warning);
        }

        .time-ago.danger {
            color: var(--danger);
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">GPS Tracking System</h1>
                <p class="page-subtitle">
                    Complete real-time GPS tracking and device management solution
                </p>
            </div>
            <div class="header-actions">
                <a class="ghost-button" href="GPS Tracking.php">
                    <i class='bx bx-map-alt'></i>
                    Live Map
                </a>
                <a class="primary-button" href="GPS Device Simulator.php">
                    <i class='bx bx-plus-circle'></i>
                    Add Device
                </a>
                <a class="ghost-button" href="admin_dashboard.php">
                    <i class='bx bxs-dashboard'></i>
                    Dashboard
                </a>
            </div>
        </div>

        <?php if ($alertDevices > 0): ?>
            <div class="alert-banner">
                <i class='bx bx-error-circle' style="font-size:24px; color:var(--danger);"></i>
                <div>
                    <strong style="color:var(--danger);">Alert: <?php echo $alertDevices; ?> device(s) need assistance!</strong>
                    <div style="font-size:13px; color:var(--muted); margin-top:4px;">Click "Live Map" to view and respond.</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Total Devices</span>
                    <div class="stat-icon blue">
                        <i class='bx bx-devices'></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $totalDevices; ?></div>
                <div class="stat-change">Registered GPS units</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Active Units</span>
                    <div class="stat-icon green">
                        <i class='bx bx-walk'></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $activeDevices; ?></div>
                <div class="stat-change">On patrol or responding</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Alerts</span>
                    <div class="stat-icon red">
                        <i class='bx bx-error'></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $alertDevices; ?></div>
                <div class="stat-change">Needs immediate attention</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Distance Today</span>
                    <div class="stat-icon orange">
                        <i class='bx bx-map'></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($totalDistance, 1); ?> km</div>
                <div class="stat-change">Total coverage</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Low Battery</span>
                    <div class="stat-icon orange">
                        <i class='bx bx-battery'></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $lowBattery; ?></div>
                <div class="stat-change">Devices below 20%</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-label">Recent Updates</span>
                    <div class="stat-icon blue">
                        <i class='bx bx-refresh'></i>
                    </div>
                </div>
                <div class="stat-value"><?php echo $recentUpdates; ?></div>
                <div class="stat-change">Updated in last hour</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-grid">
            <!-- Device List -->
            <div class="card">
                <h2 class="card-title">
                    <i class='bx bx-list-ul'></i>
                    All GPS Devices
                </h2>
                <?php if (empty($allDevices)): ?>
                    <div class="empty-state">
                        <i class='bx bx-devices'></i>
                        <p style="font-size:16px; margin:8px 0;">No GPS devices registered</p>
                        <p style="font-size:14px;">Create your first device to start tracking</p>
                        <a href="GPS Device Simulator.php" class="primary-button" style="margin-top:16px; display:inline-flex;">
                            <i class='bx bx-plus'></i>
                            Create Device
                        </a>
                    </div>
                <?php else: ?>
                    <div class="device-list">
                        <?php foreach ($allDevices as $device): 
                            $minutesAgo = $device['minutes_ago'];
                            $timeClass = $minutesAgo > 30 ? 'danger' : ($minutesAgo > 15 ? 'warning' : '');
                            $batteryClass = $device['battery'] < 20 ? 'battery-low' : ($device['battery'] < 50 ? 'battery-medium' : 'battery-high');
                        ?>
                            <div class="device-item" onclick="window.location.href='GPS Tracking.php'">
                                <div class="device-info">
                                    <div class="device-name"><?php echo htmlspecialchars($device['callsign']); ?></div>
                                    <div class="device-meta">
                                        <span><?php echo htmlspecialchars($device['unit_id']); ?></span>
                                        <span>•</span>
                                        <span class="battery-indicator <?php echo $batteryClass; ?>">
                                            <i class='bx bx-battery'></i>
                                            <?php echo $device['battery']; ?>%
                                        </span>
                                        <span>•</span>
                                        <span><?php echo number_format($device['speed'], 1); ?> km/h</span>
                                        <span>•</span>
                                        <span class="time-ago <?php echo $timeClass; ?>">
                                            <?php 
                                            if ($minutesAgo < 1) {
                                                echo 'Just now';
                                            } elseif ($minutesAgo < 60) {
                                                echo $minutesAgo . ' min ago';
                                            } else {
                                                echo floor($minutesAgo / 60) . ' hour' . (floor($minutesAgo / 60) > 1 ? 's' : '') . ' ago';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="device-status">
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $device['status'])); ?>">
                                        <?php echo htmlspecialchars($device['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div>
                <div class="card" style="margin-bottom: 24px;">
                    <h2 class="card-title">
                        <i class='bx bx-navigation'></i>
                        Quick Actions
                    </h2>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="GPS Tracking.php" class="ghost-button" style="justify-content: flex-start;">
                            <i class='bx bx-map-alt'></i>
                            View Live Map
                        </a>
                        <a href="GPS Device Management.php" class="ghost-button" style="justify-content: flex-start;">
                            <i class='bx bx-cog'></i>
                            Manage Devices
                        </a>
                        <a href="GPS Device Simulator.php" class="ghost-button" style="justify-content: flex-start;">
                            <i class='bx bx-play-circle'></i>
                            Simulate Devices
                        </a>
                        <a href="GPS Dashboard.php" class="ghost-button" style="justify-content: flex-start;">
                            <i class='bx bx-bar-chart'></i>
                            View Dashboard
                        </a>
                    </div>
                </div>

                <?php if (!empty($recentHistory)): ?>
                    <div class="card">
                        <h2 class="card-title">
                            <i class='bx bx-history'></i>
                            Recent Activity
                        </h2>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($recentHistory as $history): ?>
                                <div style="padding: 12px; background: var(--bg); border-radius: 8px; border: 1px solid var(--border);">
                                    <div style="font-weight: 600; font-size: 14px; margin-bottom: 4px;">
                                        <?php echo htmlspecialchars($history['callsign']); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--muted);">
                                        <?php echo $history['point_count']; ?> GPS points recorded
                                        <br>
                                        <span style="font-size: 11px;">
                                            <?php echo date('M d, H:i', strtotime($history['last_record'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
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
                    Monitor all GPS devices on an interactive map with real-time updates every 5 seconds. View unit locations, status, and movement patterns.
                </p>
            </a>

            <a href="GPS Device Management.php" class="feature-card">
                <div class="feature-icon">
                    <i class='bx bx-cog'></i>
                </div>
                <h3 class="feature-title">Device Management</h3>
                <p class="feature-description">
                    Add, edit, and manage GPS tracking devices. Configure device settings, assignments, and view detailed information with API documentation.
                </p>
            </a>

            <a href="GPS Device Simulator.php" class="feature-card">
                <div class="feature-icon">
                    <i class='bx bx-play-circle'></i>
                </div>
                <h3 class="feature-title">Device Simulator</h3>
                <p class="feature-description">
                    Create test GPS devices and simulate GPS updates for testing and development purposes. Perfect for system validation.
                </p>
            </a>
        </div>
    </div>
</body>
</html>

