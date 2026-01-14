<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

// Create GPS tracking table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gps_units (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unit_id VARCHAR(50) UNIQUE NOT NULL,
            callsign VARCHAR(100) NOT NULL,
            assignment VARCHAR(255) DEFAULT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            status ENUM('On Patrol', 'Responding', 'Stationary', 'Needs Assistance') DEFAULT 'On Patrol',
            speed DECIMAL(5, 2) DEFAULT 0,
            battery INT DEFAULT 100,
            distance_today DECIMAL(8, 2) DEFAULT 0,
            last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_last_ping (last_ping),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Create GPS history table for tracking movement paths
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gps_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unit_id VARCHAR(50) NOT NULL,
            latitude DECIMAL(10, 8) NOT NULL,
            longitude DECIMAL(11, 8) NOT NULL,
            speed DECIMAL(5, 2) DEFAULT 0,
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_unit_id (unit_id),
            INDEX idx_recorded_at (recorded_at),
            FOREIGN KEY (unit_id) REFERENCES gps_units(unit_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Insert demo units if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gps_units");
    $count = $stmt->fetch()['count'];
    
    if ($count == 0) {
        $demoUnits = [
            ['UNIT-VAN', 'Van', 'Transport Fleet', 14.4231, 120.9724, 'On Patrol', 25, 85, 10.0],
            ['UNIT-TAXI', 'Taxi', 'City Service', 14.4240, 120.9730, 'Responding', 40, 70, 15.0],
            ['UNIT-POLICE', 'Police car', 'Patrol', 14.4225, 120.9715, 'On Patrol', 30, 90, 12.0],
            ['UNIT-BUS', 'Bus', 'Transit Hub', 14.4210, 120.9742, 'Stationary', 0, 60, 8.5],
            ['UNIT-AMB', 'Ambulance', 'Emergency Response', 14.4255, 120.9748, 'Responding', 50, 88, 18.0],
            ['UNIT-SEDAN', 'Sedan', 'City Roads', 14.4261, 120.9712, 'On Patrol', 26, 82, 9.5],
            ['UNIT-SUV', 'SUV', 'Patrol Support', 14.4272, 120.9698, 'On Patrol', 28, 80, 11.1],
            ['UNIT-PICKUP', 'Pickup truck', 'Utility Route', 14.4182, 120.9689, 'On Patrol', 22, 76, 7.9],
            ['UNIT-JEEP', 'Jeep', 'Off-road Support', 14.4208, 120.9701, 'On Patrol', 24, 78, 10.4],
            ['UNIT-SKATE', 'Skateboard', 'Recreation Area', 14.4198, 120.9705, 'On Patrol', 12, 95, 5.0],
            ['UNIT-PRAM', 'Baby carriage / Pram', 'Community Park', 14.4187, 120.9698, 'Stationary', 0, 100, 1.0],
            ['UNIT-BIKE', 'Bicycle', 'Bike Lane', 14.4202, 120.9751, 'On Patrol', 18, 80, 9.0],
            ['UNIT-ROADBIKE', 'Road bike', 'Road Cycling Lane', 14.4171, 120.9721, 'On Patrol', 20, 78, 10.0],
            ['UNIT-EBIKE', 'E-bike', 'Eco Route', 14.4166, 120.9706, 'On Patrol', 19, 84, 8.2],
            ['UNIT-CARGO', 'Cargo bike', 'Delivery Path', 14.4159, 120.9693, 'On Patrol', 16, 72, 6.5],
            ['UNIT-MTB', 'Mountain bike', 'Trailhead', 14.4174, 120.9684, 'On Patrol', 22, 78, 11.2],
            ['UNIT-SCOOT', 'Scooter', 'Downtown', 14.4262, 120.9729, 'On Patrol', 27, 82, 10.1],
            ['UNIT-MOTORSCOOT', 'Motor scooter', 'Downtown', 14.4250, 120.9718, 'On Patrol', 24, 79, 9.2],
            ['UNIT-MOTO', 'Motorcycle', 'Rapid Response', 14.4270, 120.9702, 'Responding', 55, 76, 20.3],
            ['UNIT-DIRT', 'Dirt bike', 'Off-road Trail', 14.4149, 120.9672, 'On Patrol', 23, 74, 12.4],
            ['UNIT-FIRE', 'Fire engine', 'Station 1', 14.4190, 120.9760, 'Needs Assistance', 0, 50, 2.5],
            ['UNIT-CRANE', 'Crane', 'Construction Site', 14.4158, 120.9690, 'Stationary', 0, 65, 0.0],
            ['UNIT-FORK', 'Forklift', 'Warehouse', 14.4165, 120.9712, 'Stationary', 0, 68, 0.0],
            ['UNIT-TRACT', 'Tractor', 'Farm Road', 14.4147, 120.9652, 'On Patrol', 15, 72, 7.6],
            ['UNIT-RECYCLE', 'Recycling truck', 'Collection Route', 14.4183, 120.9736, 'On Patrol', 20, 60, 12.0],
            ['UNIT-CEMENT', 'Cement mixer', 'Build Zone', 14.4189, 120.9578, 'On Patrol', 10, 54, 6.2],
            ['UNIT-DUMP', 'Dump truck', 'Industrial Park', 14.4179, 120.9592, 'On Patrol', 14, 58, 9.3]
        ];

        $insertStmt = $pdo->prepare("\n            INSERT INTO gps_units (unit_id, callsign, assignment, latitude, longitude, status, speed, battery, distance_today, last_ping)\n            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? MINUTE))\n        ");

        $minutes = array_map(function($i){ return ($i * 3) % 13 + 1; }, range(1, count($demoUnits)));
        foreach ($demoUnits as $index => $unit) {
            $insertStmt->execute([
                $unit[0], $unit[1], $unit[2], $unit[3], $unit[4],
                $unit[5], $unit[6], $unit[7], $unit[8], $minutes[$index]
            ]);
        }
    }
} catch (PDOException $e) {
    error_log("GPS table creation error: " . $e->getMessage());
}

// Fetch units from database
try {
    $stmt = $pdo->query("
        SELECT 
            unit_id as id,
            callsign,
            assignment,
            latitude as lat,
            longitude as lng,
            status,
            speed,
            battery,
            distance_today,
            last_ping,
            TIMESTAMPDIFF(SECOND, last_ping, NOW()) as seconds_since_ping
        FROM gps_units 
        WHERE is_active = 1
        ORDER BY callsign
    ");
    $units = $stmt->fetchAll();
    
    // Format last_ping as ISO string
    foreach ($units as &$unit) {
        $unit['last_ping'] = date(DATE_ATOM, strtotime($unit['last_ping']));
    }
    
    $activeUnits = array_filter($units, fn($unit) => in_array($unit['status'], ['On Patrol', 'Responding']));
    $stationaryUnits = array_filter($units, fn($unit) => $unit['status'] === 'Stationary');
    $alertUnits = array_filter($units, fn($unit) => $unit['status'] === 'Needs Assistance');
    $totalDistance = array_reduce($units, fn($carry, $item) => $carry + ($item['distance_today'] ?? 0), 0);
    
    $unitsJson = json_encode($units, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log("GPS fetch error: " . $e->getMessage());
    $units = [];
    $unitsJson = '[]';
    $activeUnits = [];
    $stationaryUnits = [];
    $alertUnits = [];
    $totalDistance = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Tracking | CPAS</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-sA+vx6kG72QbFhFhi0uVme9C6Akb5dsQPRQmA+4bPc4=" crossorigin="">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" crossorigin="">
    <style>
        .leaflet-container { position: relative; overflow: hidden; }
        .leaflet-pane { position: absolute; z-index: 400; }
        .leaflet-tile, .leaflet-zoom-box, .leaflet-image-layer { position: absolute; }
        .leaflet-marker-icon, .leaflet-marker-shadow { position: absolute; }
        .leaflet-control-container { position: absolute; top: 0; left: 0; z-index: 1000; pointer-events: none; }
        .leaflet-top, .leaflet-bottom { position: absolute; z-index: 1000; pointer-events: none; }
        .leaflet-top { top: 0; }
        .leaflet-bottom { bottom: 0; }
        .leaflet-left { left: 0; }
        .leaflet-right { right: 0; }
        .leaflet-control { position: relative; pointer-events: auto; }
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
            max-width: 1440px;
            margin: 0 auto;
            padding: 32px clamp(16px, 4vw, 48px);
        }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: clamp(24px, 4vw, 36px);
            margin: 0;
        }

        .page-subtitle {
            color: var(--muted);
            margin-top: 4px;
            max-width: 640px;
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 14px;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 700;
        }

        .stat-trend {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            padding: 4px 10px;
            border-radius: 999px;
        }

        .trend-up {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
        }

        .trend-alert {
            background: rgba(248, 113, 113, 0.15);
            color: var(--danger);
        }

        .layout {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            gap: 20px;
            margin-top: 24px;
        }

        @media (max-width: 1100px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }

        .unit-detail-card {
            background: var(--bg);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid var(--border);
        }

        .unit-detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .unit-detail-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .unit-detail-id {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
        }

        .unit-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }

        .unit-detail-row:last-child {
            border-bottom: none;
        }

        .unit-detail-label {
            color: var(--muted);
            font-size: 14px;
        }

        .unit-detail-value {
            font-weight: 600;
            font-size: 14px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
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

        .card {
            background: var(--card);
            border-radius: 24px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
        }

        #tracker-map {
            width: 100%;
            height: 600px;
            min-height: 500px;
            border-radius: 20px;
            margin-bottom: 16px;
            background: #e5e7eb;
            position: relative;
        }

        .map-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: var(--muted);
            font-size: 14px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.9);
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .map-loading.hidden {
            display: none;
        }

        .map-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: space-between;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .filter-button {
            padding: 8px 16px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .filter-button.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }

        .map-dashboard {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .map-side-panel {
            flex: 0 0 240px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .map-side-panel h4 {
            margin: 0 0 12px;
            font-size: 16px;
        }

        .legend-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
            color: var(--text);
            background: #fff;
            border: 1px solid var(--border);
            padding: 10px 12px;
            border-radius: 12px;
        }

        .legend-item span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .legend-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .bubble-legend {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 12px;
            padding-top: 12px;
        }

        .bubble-legend div {
            text-align: center;
            font-size: 12px;
            color: var(--muted);
        }

        .map-main {
            flex: 1;
            min-width: 280px;
        }

        .map-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        @media (max-width: 900px) {
            .map-side-panel {
                flex: 1 1 100%;
                order: 2;
            }

            .map-main {
                order: 1;
            }
        }

        .navigation-panel {
            margin-top: 20px;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #f8fafc;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .navigation-panel label {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            display: block;
            margin-bottom: 6px;
        }

        .navigation-panel select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 12px;
            border: 1px solid var(--border);
            font-size: 14px;
        }

        .navigation-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .navigation-actions button {
            flex: 1;
            min-width: 140px;
        }


        .connection-status {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .status-connected {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .status-disconnected {
            background: rgba(220, 38, 38, 0.15);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.8;
                transform: scale(1.05);
            }
        }
        
        .marker-container {
            position: relative;
        }

        .marker-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            border-radius: 999px;
            background: inherit;
            opacity: 0.3;
            animation: ripple 2s infinite;
        }
        .map-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: inherit;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            font-size: 16px;
        }
        .map-icon i { font-size: 16px; color: #fff; }

        @keyframes ripple {
            0% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 0.3;
            }
            100% {
                transform: translate(-50%, -50%) scale(2);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div id="connection-status" class="connection-status status-connected">
            <i class='bx bx-wifi'></i> Live
        </div>
        <div class="page-header">
            <div>
                <h1 class="page-title">Real-time GPS Tracking</h1>
                <p class="page-subtitle">
                    Monitor patrol units, view their latest positions, and react faster to assistance flags.
                </p>
            </div>
            <div class="header-actions">
                <a class="ghost-button" href="GPS%20Device%20Simulator.php">
                    <i class='bx bx-plus-circle' style="margin-right:6px;"></i>
                    Create Device
                </a>
                <a class="ghost-button" href="GPS%20Device%20Management.php">
                    <i class='bx bx-devices' style="margin-right:6px;"></i>
                    Manage Devices
                </a>
                <a class="ghost-button" href="admin_dashboard.php">
                    <i class='bx bxs-dashboard' style="margin-right:6px;"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-label">Total Units</span>
                <span class="stat-value" id="total-count"><?php echo count($units); ?></span>
                <span class="stat-trend trend-up">
                    <i class='bx bx-map'></i>
                    Active tracking
                </span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Active Units</span>
                <span class="stat-value" id="active-count"><?php echo count($activeUnits); ?></span>
                <span class="stat-trend trend-up">
                    <i class='bx bx-walk'></i>
                    On patrol
                </span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Responding</span>
                <span class="stat-value" id="responding-count"><?php echo count(array_filter($units, fn($u) => $u['status'] === 'Responding')); ?></span>
                <span class="stat-trend trend-up">
                    <i class='bx bx-run'></i>
                    In motion
                </span>
            </div>
            <div class="stat-card">
                <span class="stat-label">Assistance Alerts</span>
                <span class="stat-value" id="alert-count"><?php echo count($alertUnits); ?></span>
                <span class="stat-trend trend-alert">
                    <i class='bx bx-error'></i>
                    Needs help
                </span>
            </div>
        </div>

        <div class="layout">
            <div class="card">
                <div class="map-dashboard">
                    <aside class="map-side-panel">
                        <div>
                            <h4>Unit Status</h4>
                            <ul id="status-legend" class="legend-list"></ul>
                        </div>
                        <div>
                            <h4>Bubble Scale</h4>
                            <div class="bubble-legend">
                                <div>
                                    <div style="width:50px;height:50px;border-radius:50%;background:#dde7fb;margin:auto;"></div>
                                    High activity
                                </div>
                                <div>
                                    <div style="width:30px;height:30px;border-radius:50%;background:#f2f5ff;margin:auto;"></div>
                                    Moderate
                                </div>
                                <div>
                                    <div style="width:16px;height:16px;border-radius:50%;background:#f9fbff;margin:auto;"></div>
                                    Low
                                </div>
                            </div>
                        </div>
                    </aside>
                    <div class="map-main">
                        <div class="map-toolbar">
                            <div class="filter-group">
                                <button class="filter-button active" data-filter="ALL">All Units</button>
                                <button class="filter-button" data-filter="On Patrol">On Patrol</button>
                                <button class="filter-button" data-filter="Responding">Jeeps</button>
                                <button class="filter-button" data-filter="Stationary">Motor</button>
                                <button class="filter-button" data-filter="Needs Assistance">Bike</button>
                            </div>
                        </div>
                        <div id="tracker-map"></div>
                        <div class="navigation-panel">
                            <div>
                                <label for="nav-start">Start Unit</label>
                                <select id="nav-start">
                                    <option value="">Select start</option>
                                </select>
                            </div>
                            <div>
                                <label for="nav-end">Destination Unit</label>
                                <select id="nav-end">
                                    <option value="">Select destination</option>
                                </select>
                            </div>
                            <div>
                                <label for="nav-date">Start Date</label>
                                <input type="date" id="nav-date" />
                            </div>
                            <div>
                                <label for="nav-time">Start Time</label>
                                <input type="time" id="nav-time" />
                            </div>
                            <div>
                                <label for="nav-avoid-highways">Route Preference</label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="checkbox" id="nav-avoid-highways" />
                                    <span>Prefer local roads (avoid highways)</span>
                                </div>
                            </div>
                            <div class="navigation-actions">
                                <button id="plot-route" class="primary-button" type="button">
                                    <i class='bx bx-navigation' style="margin-right:6px;"></i> Plot Route
                                </button>
                                <button id="clear-route" class="ghost-button" type="button">
                                    <i class='bx bx-eraser' style="margin-right:6px;"></i> Clear Route
                                </button>
                                <button id="auto-select" class="ghost-button" type="button">
                                    <i class='bx bx-select-multiple' style="margin-right:6px;"></i> Auto Select Units
                                </button>
                                <button id="quick-demo" class="primary-button" type="button">
                                    <i class='bx bx-rocket' style="margin-right:6px;"></i> Quick Demo
                                </button>
                            </div>
                            <div id="route-info" style="grid-column: 1 / -1; font-size: 13px; color: var(--muted);"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top:0; margin-bottom:16px;">Unit Information</h3>
                <div id="unit-details-panel" style="max-height: calc(100vh - 300px); overflow-y: auto;">
                    <div style="text-align:center; padding:40px 20px; color:var(--muted);">
                        <i class='bx bx-map' style="font-size:48px; margin-bottom:12px; opacity:0.5;"></i>
                        <p>Click on a unit marker to view details</p>
                </div>
            </div>
        </div>
        </div>
    </div>


    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-o9N1j7kPdLQ94X3bA7S3tLhufCfeU5hQ6FQ4J0u0A64=" crossorigin=""></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    <script>
        // Simple Leaflet GPS map using your PHP data
        const units = <?php echo $unitsJson; ?>;
        let routingControl = null;
        let customRouteLine = null;
        let mapInstance = null;

        const statusColors = {
            'On Patrol': '#1d4ed8',
            'Stationary': '#94a3b8',
            'Responding': '#16a34a',
            'Needs Assistance': '#dc2626'
        };
        const statusOrder = ['On Patrol', 'Responding', 'Stationary', 'Needs Assistance'];

        const timeFormatter = new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' });

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getUnitSubtype(unit) {
            const name = String(unit.callsign || '').toLowerCase();
            const map = {
                'sports car':'car-sports', 'electric car':'car-electric', 'police suv':'car-police', 'taxi sedan':'car-taxi', 'delivery van':'car-delivery',
                'sedan':'car-sedan', 'hatchback':'car-hatch', 'suv':'car-suv', 'pickup':'car-pickup', 'minivan':'car-minivan', 'van':'car-van', 'bus':'car-bus', 'taxi':'car-taxi', 'ambulance':'car-ambulance', 'police':'car-police', 'delivery':'car-delivery', 'jeep':'car-jeep', 'jeepney':'car-jeepney',
                'bicycle':'bike-bicycle', 'bike':'bike-bicycle', 'road bike':'bike-road', 'hybrid bike':'bike-hybrid', 'bmx':'bike-bmx', 'e-bike':'bike-ebike', 'ebike':'bike-ebike', 'cargo bike':'bike-cargo', 'tricycle':'bike-tricycle', 'scooter':'bike-scooter', 'motor scooter':'bike-motorscooter', 'motorbike':'bike-motorcycle', 'motorcycle':'bike-motorcycle', 'dirt bike':'bike-dirt', 'mtb':'bike-mtb'
            };
            for (const key in map) { if (name.includes(key)) return map[key]; }
            return null;
        }

        function getSubtypeStyle(subtype, baseColor) {
            const overrides = {
                'car-ambulance':'#dc2626',
                'car-police':'#1d4ed8',
                'car-taxi':'#f59e0b',
                'car-bus':'#ea580c',
                'car-delivery':'#6b7280',
                'car-electric':'#0ea5e9',
                'car-sports':'#ef4444',
                'car-jeep':'#f97316',
                'car-jeepney':'#ea580c',
                'bike-bicycle':'#16a34a',
                'bike-road':'#22c55e',
                'bike-hybrid':'#22c55e',
                'bike-bmx':'#10b981',
                'bike-ebike':'#7c3aed',
                'bike-cargo':'#10b981',
                'bike-tricycle':'#16a34a',
                'bike-scooter':'#0ea5e9',
                'bike-motorscooter':'#0ea5e9',
                'bike-motorcycle':'#0ea5e9',
                'bike-dirt':'#0ea5e9',
                'bike-mtb':'#22c55e'
            };
            const sizeOverrides = {
                'car-ambulance':30,
                'car-police':30,
                'car-bus':30,
                'car-sports':28,
                'car-jeep':28,
                'car-jeepney':30,
                'bike-motorcycle':26,
                'bike-dirt':26,
                'bike-scooter':26
            };
            const bg = overrides[subtype] || baseColor;
            const sz = sizeOverrides[subtype] || 28;
            return `background:${bg};width:${sz}px;height:${sz}px;`;
        }

        function getIconHtml(subtype, style) {
            const icons = {
                'car-sedan':'🚗', 'car-hatch':'🚙', 'car-suv':'🚙', 'car-pickup':'🛻', 'car-minivan':'🚐', 'car-van':'🚐', 'car-bus':'🚌', 'car-taxi':'🚕', 'car-ambulance':'🚑', 'car-police':'🚓', 'car-delivery':'🚚', 'car-electric':'⚡🚗', 'car-sports':'🏎️', 'car-jeep':'🚙', 'car-jeepney':'🚍',
                'bike-bicycle':'🚲', 'bike-road':'🚴', 'bike-hybrid':'🚴', 'bike-bmx':'🚲', 'bike-ebike':'⚡🚲', 'bike-cargo':'📦🚲', 'bike-tricycle':'🚲', 'bike-scooter':'🛵', 'bike-motorscooter':'🛵', 'bike-motorcycle':'🏍️', 'bike-dirt':'🏍️', 'bike-mtb':'🚵'
            };
            if (subtype === 'car-jeep') {
                return `<div class="map-icon" style="${style}">
                    <svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="10" width="14" height="6" rx="1.2" fill="white"/>
                        <path d="M7 10 L10 7 H15 L17 10 Z" fill="white"/>
                        <circle cx="8" cy="17" r="2" fill="#111"/>
                        <circle cx="17" cy="17" r="2" fill="#111"/>
                    </svg>
                </div>`;
            }
            const emoji = icons[subtype];
            if (!emoji) return null;
            return `<div class="map-icon" style="${style}">${emoji}</div>`;
        }

        function formatTime(value) {
            const date = new Date(value);
            return Number.isNaN(date.getTime()) ? '—' : timeFormatter.format(date);
        }

        function getStatusBadgeClass(status) {
            switch (status) {
                case 'On Patrol': return 'status-patrol';
                case 'Responding': return 'status-responding';
                case 'Stationary': return 'status-stationary';
                case 'Needs Assistance': return 'status-assist';
                default: return 'status-patrol';
            }
        }

        function showUnitDetails(unit) {
            const panel = document.getElementById('unit-details-panel');
            if (!panel) return;

            const statusColor = statusColors[unit.status] || '#1d4ed8';
            const batteryPercent = Number(unit.battery) || 0;
            const batteryColor = batteryPercent > 50 ? 'var(--success)' : batteryPercent > 20 ? 'var(--warning)' : 'var(--danger)';

            panel.innerHTML = `
                <div class="unit-detail-card">
                    <div class="unit-detail-header">
                        <div>
                            <h4 class="unit-detail-title">${escapeHtml(unit.callsign)}</h4>
                            <div class="unit-detail-id">${escapeHtml(unit.id)}</div>
                        </div>
                        <span class="status-badge ${getStatusBadgeClass(unit.status)}" style="background:${statusColor}15; color:${statusColor};">
                            ${escapeHtml(unit.status)}
                        </span>
                    </div>
                    <div class="unit-detail-row">
                        <span class="unit-detail-label">Assignment</span>
                        <span class="unit-detail-value">${escapeHtml(unit.assignment || 'N/A')}</span>
                    </div>
                    <div class="unit-detail-row">
                        <span class="unit-detail-label">Location</span>
                        <span class="unit-detail-value" style="font-size:12px;">${Number(unit.lat).toFixed(6)}, ${Number(unit.lng).toFixed(6)}</span>
                    </div>
                    <div class="unit-detail-row">
                        <span class="unit-detail-label">Speed</span>
                        <span class="unit-detail-value">${Number(unit.speed) || 0} km/h</span>
                    </div>
                    <div class="unit-detail-row">
                        <span class="unit-detail-label">Battery</span>
                        <span class="unit-detail-value" style="color:${batteryColor};">${batteryPercent}%</span>
                    </div>
                    <div class="unit-detail-row">
                        <span class="unit-detail-label">Distance Today</span>
                        <span class="unit-detail-value">${Number(unit.distance_today || 0).toFixed(1)} km</span>
                    </div>
                    <div class="unit-detail-row">
                        <span class="unit-detail-label">Last Update</span>
                        <span class="unit-detail-value">${formatTime(unit.last_ping)}</span>
                    </div>
                </div>
            `;
        }

        function updateStats(currentUnits) {
            const totalCount = currentUnits.length;
            const activeCount = currentUnits.filter(u => u.status === 'On Patrol' || u.status === 'Responding').length;
            const respondingCount = currentUnits.filter(u => u.status === 'Responding').length;
            const alertCount = currentUnits.filter(u => u.status === 'Needs Assistance').length;

            const totalEl = document.getElementById('total-count');
            const activeEl = document.getElementById('active-count');
            const respondingEl = document.getElementById('responding-count');
            const alertEl = document.getElementById('alert-count');

            if (totalEl) totalEl.textContent = totalCount;
            if (activeEl) activeEl.textContent = activeCount;
            if (respondingEl) respondingEl.textContent = respondingCount;
            if (alertEl) alertEl.textContent = alertCount;
        }

        function renderStatusLegend() {
            const legendEl = document.getElementById('status-legend');
            if (!legendEl) return;
            const legendItems = statusOrder.map(status => {
                const count = units.filter(u => u.status === status).length;
                const color = statusColors[status] || '#1d4ed8';
                return `
                    <li class="legend-item">
                        <span>
                            <span class="legend-dot" style="background:${color};"></span>
                            ${status}
                        </span>
                        <strong>${count}</strong>
                    </li>
                `;
            }).join('');
            legendEl.innerHTML = legendItems;
        }

        document.addEventListener('DOMContentLoaded', function () {
            function init() {
                const mapDiv = document.getElementById('tracker-map');
                if (!mapDiv) return;
                const defaultCenter = units[0] ? [units[0].lat, units[0].lng] : [14.42, 120.97];
                mapInstance = L.map('tracker-map').setView(defaultCenter, 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(mapInstance);
                const markersLayer = L.layerGroup().addTo(mapInstance);
                let currentFilter = 'ALL';
                let typeFilter = 'ALL';
                const connectionEl = document.getElementById('connection-status');
                function setConnection(connected) {
                    if (!connectionEl) return;
                    connectionEl.classList.toggle('status-connected', connected);
                    connectionEl.classList.toggle('status-disconnected', !connected);
                    connectionEl.innerHTML = connected ? "<i class='bx bx-wifi'></i> Live" : "<i class='bx bx-wifi-off'></i> Offline";
                }
                function renderMarkers() {
                    markersLayer.clearLayers();
                    const statusFiltered = units.filter(u => currentFilter === 'ALL' ? true : u.status === currentFilter);
                    const filtered = statusFiltered.filter(u => {
                        if (typeFilter === 'ALL') return true;
                        const st = getUnitSubtype(u) || '';
                        if (typeFilter === 'car') return st.startsWith('car-');
                        if (typeFilter === 'jeep') return st === 'car-jeep' || st === 'car-jeepney';
                        if (typeFilter === 'motor') return st === 'bike-motorcycle' || st === 'bike-dirt' || st === 'bike-motorscooter' || st === 'bike-scooter';
                        if (typeFilter === 'bike') return st.startsWith('bike-');
                        return true;
                    });
                    const latLngs = [];
                    filtered.forEach(unit => {
                        const color = statusColors[unit.status] || '#1d4ed8';
                        const subtype = getUnitSubtype(unit);
                        const style = getSubtypeStyle(subtype, color);
                        const iconHtml = getIconHtml(subtype, style);
                        let marker;
                        if (iconHtml) {
                            const icon = L.divIcon({ className: '', html: iconHtml, iconSize: [28, 28], iconAnchor: [14, 14] });
                            marker = L.marker([unit.lat, unit.lng], { icon }).addTo(markersLayer);
                        } else {
                            marker = L.circleMarker([unit.lat, unit.lng], { radius: 8, color, fillColor: color, fillOpacity: 0.9 }).addTo(markersLayer);
                        }
                        marker.bindPopup(`<strong>${escapeHtml(unit.callsign)}</strong><br/>Status: ${escapeHtml(unit.status)}<br/>Speed: ${Number(unit.speed) || 0} km/h<br/>Battery: ${Number(unit.battery) || 0}%<br/>Last ping: ${formatTime(unit.last_ping)}`);
                        marker.on('click', () => showUnitDetails(unit));
                        latLngs.push([unit.lat, unit.lng]);
                    });
                    if (latLngs.length > 0) {
                        const bounds = L.latLngBounds(latLngs);
                        mapInstance.fitBounds(bounds.pad(0.15));
                    } else {
                        mapInstance.setView(defaultCenter, 13);
                    }
                    updateStats(units);
                    renderStatusLegend();
                }
                renderMarkers();
                const startSelect = document.getElementById('nav-start');
                const endSelect = document.getElementById('nav-end');
                const plotRouteBtn = document.getElementById('plot-route');
                const clearRouteBtn = document.getElementById('clear-route');
                const autoSelectBtn = document.getElementById('auto-select');
                const quickDemoBtn = document.getElementById('quick-demo');
                function populateNavigationOptions() {
                    if (!startSelect || !endSelect) return;
                    const optionTemplate = units.map(unit => `<option value="${escapeHtml(unit.id)}">${escapeHtml(unit.callsign)} (${escapeHtml(unit.id)})</option>`).join('');
                    startSelect.innerHTML = '<option value="">Select start</option>' + optionTemplate;
                    endSelect.innerHTML = '<option value="">Select destination</option>' + optionTemplate;
                    const infoEl = document.getElementById('route-info');
                    if (infoEl) {
                        if (units.length < 2) {
                            infoEl.textContent = 'Add at least two active units to enable routing. Use GPS Device Management or Simulator to create devices.';
                        } else if (!startSelect.value || !endSelect.value) {
                            infoEl.textContent = 'Select start and destination units, then optionally choose date/time to see ETA.';
                        } else {
                            infoEl.textContent = '';
                        }
                    }
                }
                populateNavigationOptions();
                async function fetchLatest() {
                    try {
                        const res = await fetch('api/gps_data.php', { headers: { 'Accept': 'application/json' } });
                        if (!res.ok) throw new Error('Network');
                        const data = await res.json();
                        if (!data || !data.success || !Array.isArray(data.units)) throw new Error('Invalid');
                        units.length = 0;
                        data.units.forEach(u => units.push(u));
                        setConnection(true);
                        renderMarkers();
                        populateNavigationOptions();
                    } catch (e) {
                        setConnection(false);
                    }
                }
                setInterval(fetchLatest, 5000);
                function isValidRouteSelection() {
                    if (!startSelect || !endSelect) return false;
                    const s = startSelect.value;
                    const e = endSelect.value;
                    return Boolean(s && e && s !== e);
                }
                function updatePlotButtonState() {
                    if (!plotRouteBtn) return;
                    const valid = isValidRouteSelection();
                    plotRouteBtn.disabled = !valid;
                    plotRouteBtn.classList.toggle('btn-disabled', !valid);
                }
                if (startSelect && endSelect && !startSelect.value && !endSelect.value && units.length >= 2) {
                    startSelect.value = units[0].id;
                    endSelect.value = units[1].id;
                }
                updatePlotButtonState();
                startSelect?.addEventListener('change', updatePlotButtonState);
                endSelect?.addEventListener('change', updatePlotButtonState);
                function autoSelectUnits() {
                    const infoEl = document.getElementById('route-info');
                    if (!startSelect || !endSelect) return;
                    if (units.length < 2) {
                        if (infoEl) infoEl.textContent = 'Add at least two active units to enable routing. Use GPS Device Management or Simulator to create devices.';
                        return;
                    }
                    const score = (u) => u.status === 'Responding' ? 2 : (u.status === 'On Patrol' ? 1 : 0);
                    const candidates = [...units].sort((a,b) => score(b) - score(a));
                    startSelect.value = candidates[0].id;
                    const second = candidates.find(u => u.id !== startSelect.value) || candidates[1];
                    endSelect.value = second?.id || '';
                    updatePlotButtonState();
                    if (infoEl) infoEl.textContent = 'Auto-selected units. You can change selections or plot the route.';
                }
                autoSelectBtn?.addEventListener('click', autoSelectUnits);
                function quickDemo() {
                    if (!startSelect || !endSelect) return;
                    if (units.length < 2) { alert('Need at least two units'); return; }
                    if (!startSelect.value || !endSelect.value || startSelect.value === endSelect.value) {
                        autoSelectUnits();
                    }
                    const s = units.find(u => u.id === startSelect.value) || units[0];
                    const e = units.find(u => u.id === endSelect.value) || units[1];
                    const icon = L.divIcon({ className: '', html: '<div class="map-icon" style="background:#2563eb;width:28px;height:28px;">🚗</div>', iconSize: [28,28], iconAnchor: [14,14] });
                    const marker = L.marker([s.lat, s.lng], { icon }).addTo(mapInstance);
                    const steps = 120;
                    const dlat = (e.lat - s.lat) / steps;
                    const dlng = (e.lng - s.lng) / steps;
                    let i = 0;
                    const timer = setInterval(function(){
                        i++;
                        const lat = s.lat + dlat * i;
                        const lng = s.lng + dlng * i;
                        marker.setLatLng([lat, lng]);
                        if (i >= steps) { clearInterval(timer); }
                    }, 150);
                }
                quickDemoBtn?.addEventListener('click', quickDemo);
                function plotRoute() {
                    if (!isValidRouteSelection()) return;
                    const startId = startSelect.value;
                    const endId = endSelect.value;
                    const startUnit = units.find(u => u.id === startId);
                    const endUnit = units.find(u => u.id === endId);
                    if (!startUnit || !endUnit) { alert('Unable to find selected units.'); return; }
                    const avoid = document.getElementById('nav-avoid-highways')?.checked;
                    const infoEl = document.getElementById('route-info');
                    if (avoid && window.ORS_API_KEY) {
                        if (routingControl) { routingControl.remove(); routingControl = null; }
                        if (customRouteLine) { mapInstance.removeLayer(customRouteLine); customRouteLine = null; }
                        const body = {
                            coordinates: [[startUnit.lng, startUnit.lat],[endUnit.lng, endUnit.lat]],
                            avoid_features: ['highways'],
                            instructions: false
                        };
                        fetch('https://api.openrouteservice.org/v2/directions/driving-car', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Authorization': window.ORS_API_KEY },
                            body: JSON.stringify(body)
                        }).then(r => r.json()).then(data => {
                            const route = data && data.routes && data.routes[0];
                            if (!route) { throw new Error('No route'); }
                            const coords = route.geometry && route.geometry.coordinates || [];
                            const latlngs = coords.map(c => [c[1], c[0]]);
                            customRouteLine = L.polyline(latlngs, { color: '#2563eb', weight: 5, opacity: 0.8 }).addTo(mapInstance);
                            mapInstance.fitBounds(customRouteLine.getBounds(), { padding: [20,20] });
                            const meters = (route.summary && route.summary.distance) || 0;
                            const seconds = (route.summary && route.summary.duration) || 0;
                            const km = (meters/1000).toFixed(2);
                            const h = Math.floor(seconds/3600);
                            const m = Math.round((seconds%3600)/60);
                            const dur = h ? (h + 'h ' + m + 'm') : (m + 'm');
                            const d = document.getElementById('nav-date')?.value || '';
                            const t = document.getElementById('nav-time')?.value || '';
                            let extra = ' • Preference: local roads';
                            if (d && t) {
                                const startTs = new Date(d + 'T' + t);
                                const etaTs = new Date(startTs.getTime() + seconds*1000);
                                extra += ' • Start: ' + startTs.toLocaleString() + ' • ETA: ' + etaTs.toLocaleString();
                            }
                            if (infoEl) infoEl.textContent = 'Distance: ' + km + ' km • Duration: ' + dur + extra;
                        }).catch(() => {
                            if (infoEl) infoEl.textContent = 'Unable to compute highway-avoiding route. Falling back to standard routing.';
                            useDefaultRouting();
                        });
                        return;
                    }
                    if (avoid && !window.ORS_API_KEY) {
                        if (infoEl) infoEl.textContent = 'To avoid highways, set window.ORS_API_KEY and retry. Using standard routing.';
                    }
                    useDefaultRouting();
                    function useDefaultRouting(){
                        if (!L.Routing) { var s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js'; document.head.appendChild(s); s.onload = () => plotRoute(); return; }
                        if (customRouteLine) { mapInstance.removeLayer(customRouteLine); customRouteLine = null; }
                        if (routingControl) { routingControl.remove(); }
                        routingControl = L.Routing.control({ waypoints: [ L.latLng(startUnit.lat, startUnit.lng), L.latLng(endUnit.lat, endUnit.lng) ], draggableWaypoints: false, addWaypoints: false, fitSelectedRoutes: true, routeWhileDragging: false, show: false, lineOptions: { styles: [{ color: '#2563eb', weight: 5, opacity: 0.8 }] } }).addTo(mapInstance);
                        routingControl.on('routesfound', function(e){
                            const r = e.routes && e.routes[0];
                            if (!r) return;
                            const meters = r.summary.totalDistance || 0;
                            const seconds = r.summary.totalTime || 0;
                            const km = (meters/1000).toFixed(2);
                            const h = Math.floor(seconds/3600);
                            const m = Math.round((seconds%3600)/60);
                            const dur = h ? (h + 'h ' + m + 'm') : (m + 'm');
                            const d = document.getElementById('nav-date')?.value || '';
                            const t = document.getElementById('nav-time')?.value || '';
                            let extra = '';
                            if (d && t) {
                                const startTs = new Date(d + 'T' + t);
                                const etaTs = new Date(startTs.getTime() + seconds*1000);
                                extra = ' • Start: ' + startTs.toLocaleString() + ' • ETA: ' + etaTs.toLocaleString();
                            }
                            if (infoEl) infoEl.textContent = 'Distance: ' + km + ' km • Duration: ' + dur + extra;
                        });
                    }
                }
                function clearRoute() { if (routingControl) { routingControl.remove(); routingControl = null; } if (customRouteLine) { mapInstance.removeLayer(customRouteLine); customRouteLine = null; } const infoEl = document.getElementById('route-info'); if (infoEl) infoEl.textContent = ''; }
                plotRouteBtn?.addEventListener('click', plotRoute);
                clearRouteBtn?.addEventListener('click', clearRoute);
                document.querySelectorAll('#status-filter .filter-button').forEach(button => {
                    button.addEventListener('click', () => {
                        document.querySelectorAll('#status-filter .filter-button').forEach(btn => btn.classList.remove('active'));
                        button.classList.add('active');
                        currentFilter = button.dataset.filter || 'ALL';
                        renderMarkers();
                    });
                });
                document.querySelectorAll('#type-filter .filter-button').forEach(button => {
                    button.addEventListener('click', () => {
                        document.querySelectorAll('#type-filter .filter-button').forEach(btn => btn.classList.remove('active'));
                        button.classList.add('active');
                        typeFilter = button.dataset.type || 'ALL';
                        renderMarkers();
                    });
                });
                window.addEventListener('resize', () => { setTimeout(() => mapInstance?.invalidateSize(), 100); });
            }
            if (typeof L === 'undefined') {
                var s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js';
                document.head.appendChild(s);
                s.onload = function() { init(); };
                return;
            }
            init();
        });
    </script>
</body>
</html>
