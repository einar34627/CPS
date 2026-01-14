<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$currentUserId = $_SESSION['user_id'];
$flash = ['type' => null, 'message' => null];

function computeRouteDistanceKm(array $points): float
{
    $total = 0.0;
    $earthRadius = 6371;

    for ($i = 1, $count = count($points); $i < $count; $i++) {
        [$lat1, $lon1] = array_map('floatval', $points[$i - 1]);
        [$lat2, $lon2] = array_map('floatval', $points[$i]);

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $total += $earthRadius * $c;
    }

    return round($total, 2);
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS route_mappings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            route_name VARCHAR(255) NOT NULL,
            zone VARCHAR(255) DEFAULT NULL,
            patrol_type VARCHAR(100) DEFAULT NULL,
            priority VARCHAR(50) DEFAULT 'Normal',
            status VARCHAR(50) DEFAULT 'PLANNED',
            schedule_window VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            coordinates LONGTEXT NOT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {
    $flash = ['type' => 'error', 'message' => 'Warning: Failed to verify route table.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $routeName = trim($_POST['route_name'] ?? '');
    $zone = trim($_POST['zone'] ?? '');
    $patrolType = trim($_POST['patrol_type'] ?? '');
    $priority = trim($_POST['priority'] ?? 'Normal');
    $status = trim($_POST['status'] ?? 'PLANNED');
    $scheduleWindow = trim($_POST['schedule_window'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $coordinatesRaw = $_POST['coordinates'] ?? '[]';
    $decodedCoordinates = json_decode($coordinatesRaw, true);

    if (!$routeName || !is_array($decodedCoordinates) || count($decodedCoordinates) < 2) {
        $flash = ['type' => 'error', 'message' => 'Add at least two waypoints and provide a route name.'];
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO route_mappings
                    (route_name, zone, patrol_type, priority, status, schedule_window, notes, coordinates, created_by)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $routeName,
                $zone ?: null,
                $patrolType ?: null,
                $priority ?: 'Normal',
                $status ?: 'PLANNED',
                $scheduleWindow ?: null,
                $notes ?: null,
                json_encode($decodedCoordinates),
                $currentUserId
            ]);

            header("Location: Route%20Mapping.php?status=success");
            exit();
        } catch (Exception $e) {
            $flash = ['type' => 'error', 'message' => 'Unable to save the route. Please try again later.'];
        }
    }
}

if (!$flash['type'] && isset($_GET['status']) && $_GET['status'] === 'success') {
    $flash = ['type' => 'success', 'message' => 'Route saved successfully.'];
}

// Handle sample route generation
if (isset($_GET['generate_samples']) && $_GET['generate_samples'] === '1') {
    try {
        // Check if routes already exist
        $checkStmt = $pdo->query("SELECT COUNT(*) as count FROM route_mappings");
        $count = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        
        if ($count > 0) {
            header("Location: Route%20Mapping.php?status=exists");
            exit();
        }
        
        // Sample routes data (Manila, Philippines area)
        $sampleRoutes = [
            [
                'route_name' => 'Zone 1 - Caloocan North Patrol',
                'zone' => 'Caloocan',
                'patrol_type' => 'Vehicle',
                'priority' => 'High',
                'status' => 'Active',
                'schedule_window' => 'Daily 18:00 - 23:00',
                'notes' => 'High crime area, requires frequent monitoring',
                'coordinates' => [
                    [14.6543, 120.9842],
                    [14.6560, 120.9865],
                    [14.6580, 120.9880],
                    [14.6600, 120.9900],
                    [14.6620, 120.9920]
                ]
            ],
            [
                'route_name' => 'Zone 2 - Quezon City Central',
                'zone' => 'Quezon City',
                'patrol_type' => 'Foot',
                'priority' => 'Normal',
                'status' => 'Ongoing',
                'schedule_window' => 'Daily 06:00 - 14:00',
                'notes' => 'Residential area, community engagement focus',
                'coordinates' => [
                    [14.6760, 121.0437],
                    [14.6780, 121.0450],
                    [14.6800, 121.0470],
                    [14.6820, 121.0490]
                ]
            ],
            [
                'route_name' => 'Zone 3 - Manila Bay Coastal',
                'zone' => 'Manila',
                'patrol_type' => 'Motorcycle',
                'priority' => 'Normal',
                'status' => 'Planned',
                'schedule_window' => 'Weekends 08:00 - 16:00',
                'notes' => 'Tourist area, weekend patrol route',
                'coordinates' => [
                    [14.5842, 120.9792],
                    [14.5860, 120.9810],
                    [14.5880, 120.9830],
                    [14.5900, 120.9850],
                    [14.5920, 120.9870]
                ]
            ],
            [
                'route_name' => 'Zone 4 - Business District',
                'zone' => 'Makati',
                'patrol_type' => 'Vehicle',
                'priority' => 'High',
                'status' => 'Active',
                'schedule_window' => 'Daily 20:00 - 02:00',
                'notes' => 'Night shift, high-value target area',
                'coordinates' => [
                    [14.5547, 121.0244],
                    [14.5560, 121.0260],
                    [14.5580, 121.0280],
                    [14.5600, 121.0300]
                ]
            ],
            [
                'route_name' => 'Zone 5 - Residential Perimeter',
                'zone' => 'Pasig',
                'patrol_type' => 'Bicycle',
                'priority' => 'Low',
                'status' => 'Planned',
                'schedule_window' => 'Daily 10:00 - 18:00',
                'notes' => 'Low priority residential area',
                'coordinates' => [
                    [14.5764, 121.0851],
                    [14.5780, 121.0870],
                    [14.5800, 121.0890]
                ]
            ]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO route_mappings
                (route_name, zone, patrol_type, priority, status, schedule_window, notes, coordinates, created_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($sampleRoutes as $route) {
            $stmt->execute([
                $route['route_name'],
                $route['zone'],
                $route['patrol_type'],
                $route['priority'],
                $route['status'],
                $route['schedule_window'],
                $route['notes'],
                json_encode($route['coordinates']),
                $currentUserId
            ]);
        }
        
        header("Location: Route%20Mapping.php?status=samples_created");
        exit();
    } catch (Exception $e) {
        $flash = ['type' => 'error', 'message' => 'Failed to generate sample routes: ' . $e->getMessage()];
    }
}

if (isset($_GET['status']) && $_GET['status'] === 'exists') {
    $flash = ['type' => 'error', 'message' => 'Routes already exist. Clear existing routes first if you want to generate new samples.'];
}

if (isset($_GET['status']) && $_GET['status'] === 'samples_created') {
    $flash = ['type' => 'success', 'message' => 'Sample routes generated successfully! You can now export the data.'];
}

$rawRoutes = [];
try {
    $stmt = $pdo->query("SELECT * FROM route_mappings ORDER BY created_at DESC LIMIT 100");
    $rawRoutes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $rawRoutes = [];
}

$preparedRoutes = [];
$activeRoutes = 0;
$highPriorityRoutes = 0;
$totalDistance = 0;

foreach ($rawRoutes as $route) {
    $decoded = json_decode($route['coordinates'] ?? '[]', true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $distance = computeRouteDistanceKm($decoded);
    $totalDistance += $distance;

    $status = strtoupper($route['status'] ?? 'PLANNED');
    $priorityLevel = strtoupper($route['priority'] ?? 'NORMAL');

    if (in_array($status, ['ACTIVE', 'ONGOING', 'IN PROGRESS'])) {
        $activeRoutes++;
    }

    if ($priorityLevel === 'HIGH') {
        $highPriorityRoutes++;
    }

    $preparedRoutes[] = [
        'id' => $route['id'],
        'route_name' => $route['route_name'] ?? 'Unnamed Route',
        'zone' => $route['zone'] ?? 'Not set',
        'patrol_type' => $route['patrol_type'] ?? 'Standard',
        'priority' => ucfirst(strtolower($route['priority'] ?? 'Normal')),
        'status' => ucfirst(strtolower($route['status'] ?? 'Planned')),
        'schedule_window' => $route['schedule_window'] ?? '',
        'notes' => $route['notes'] ?? '',
        'created_at' => $route['created_at'] ?? '',
        'coordinates' => $decoded,
        'points' => count($decoded),
        'distance' => $distance
    ];
}

$coverageAverage = count($preparedRoutes) ? round($totalDistance / count($preparedRoutes), 2) : 0;
$lastUpdated = $preparedRoutes[0]['created_at'] ?? null;

$routesForJs = json_encode($preparedRoutes, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Mapping | CPAS</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous" onerror="this.onerror=null; this.href='https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';">
    <style>
        :root {
            --bg: #f5f7fb;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --primary: #2563eb;
            --primary-light: #dbeafe;
            --danger: #dc2626;
            --success: #16a34a;
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
            padding: 32px clamp(16px, 4vw, 56px);
            max-width: 1440px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        .page-header h1 {
            margin: 0;
            font-size: clamp(24px, 3vw, 34px);
        }

        .page-header p {
            margin: 4px 0 0;
            color: var(--muted);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .btn {
            border: none;
            border-radius: 999px;
            padding: 10px 18px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .btn:disabled,
        .btn[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .btn.btn-disabled {
            opacity: 0.5;
            cursor: pointer;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background: #ecfdf5;
            color: var(--success);
        }

        .alert-error {
            background: #fef2f2;
            color: var(--danger);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 20px;
            border: 1px solid var(--border);
        }

        .stat-label {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
        }

        .stat-hint {
            margin-top: 8px;
            font-size: 13px;
            color: var(--muted);
        }

        .layout {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .panel {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
        }

        #route-map {
            width: 100%;
            height: 420px;
            border-radius: 16px;
            margin-bottom: 16px;
            background: #e2e8f0;
            border: 1px solid var(--border);
            position: relative;
        }

        #route-map.leaflet-container {
            z-index: 1;
        }

        .map-tools {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .map-summary {
            margin-top: 16px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 14px;
            border: 1px dashed var(--border);
        }

        .map-summary h3 {
            margin: 0 0 10px;
            font-size: 16px;
        }

        ul.waypoints {
            margin: 0;
            padding-left: 20px;
            max-height: 140px;
            overflow-y: auto;
            font-size: 14px;
            color: var(--muted);
        }

        form .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .form-group label {
            font-weight: 600;
            font-size: 14px;
        }

        .input, .select, textarea {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 14px;
            font-family: inherit;
            background: #fff;
        }

        textarea {
            resize: vertical;
            min-height: 96px;
        }

        .routes-table {
            width: 100%;
            border-collapse: collapse;
        }

        .routes-table th, .routes-table td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
            text-align: left;
        }

        .routes-table tbody tr {
            cursor: pointer;
        }

        .routes-table tbody tr:hover {
            background: #f8fafc;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            font-size: 12px;
            border-radius: 999px;
            font-weight: 600;
        }

        .badge.high { background: #fee2e2; color: #b91c1c; }
        .badge.normal { background: #fef3c7; color: #b45309; }
        .badge.low { background: #dcfce7; color: #15803d; }
        .badge.status-active { background: #dbeafe; color: #1d4ed8; }
        .badge.status-planned { background: #ede9fe; color: #6d28d9; }
        .badge.status-complete { background: #ecfdf5; color: #047857; }

        .table-panel {
            margin-bottom: 32px;
        }

        .table-panel h2 {
            margin-bottom: 12px;
        }

        .table-panel p {
            margin-top: 0;
            margin-bottom: 16px;
            color: var(--muted);
        }

        @media (max-width: 1024px) {
            .layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .map-tools {
                flex-direction: column;
            }

            #route-map {
                height: 320px;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="page-header">
            <div>
                <p><a href="admin_dashboard.php" style="text-decoration:none;color:var(--muted);"><i class='bx bx-arrow-back'></i> Back to dashboard</a></p>
                <h1>Patrol Route Mapping</h1>
                <p>Design, annotate, and monitor patrol routes with live spatial context.</p>
            </div>
            <div class="actions">
                <a href="#route-form" class="btn btn-primary"><i class='bx bx-map-pin'></i>Save New Route</a>
                <button class="btn btn-outline" onclick="window.print()"><i class='bx bx-printer'></i>Print Summary</button>
            </div>
        </div>

        <?php if ($flash['type']): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
                <i class='bx <?php echo $flash['type'] === 'success' ? 'bx-check-shield' : 'bx-error'; ?>'></i>
                <span><?php echo htmlspecialchars($flash['message']); ?></span>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Active / Ongoing Routes</div>
                <div class="stat-value"><?php echo $activeRoutes; ?></div>
                <div class="stat-hint"><?php echo count($preparedRoutes); ?> total routes</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">High Priority Coverage</div>
                <div class="stat-value"><?php echo $highPriorityRoutes; ?></div>
                <div class="stat-hint">Flagged for urgent monitoring</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Route Length</div>
                <div class="stat-value"><?php echo $coverageAverage; ?><span style="font-size:16px;margin-left:4px;">km</span></div>
                <div class="stat-hint">based on mapped geometry</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Last Update</div>
                <div class="stat-value" style="font-size:22px;">
                    <?php echo $lastUpdated ? date('M d, Y', strtotime($lastUpdated)) : 'No data'; ?>
                </div>
                <div class="stat-hint">automatic when new route is saved</div>
            </div>
        </div>

        <div class="layout">
            <div class="panel">
                <h2 style="margin-top:0;">Interactive Map Builder</h2>
                <p style="color:var(--muted); margin-top:4px;">Click on the map to drop waypoints. Use the controls below to manage the current route.</p>
                <div id="route-map"></div>
                <div class="map-tools">
                    <input type="text" class="input" id="search-query" placeholder="Search place or address..." style="flex:1; min-width:220px;">
                    <button class="btn btn-outline" type="button" id="search-go"><i class='bx bx-search'></i>Search</button>
                    <button class="btn btn-outline" type="button" id="locate-me"><i class='bx bx-target-lock'></i>Locate Me</button>
                    <button class="btn btn-outline" type="button" id="undo-point"><i class='bx bx-undo'></i>Undo</button>
                    <button class="btn btn-outline" type="button" id="clear-route"><i class='bx bx-trash'></i>Clear</button>
                    <button class="btn btn-outline" type="button" id="finish-route"><i class='bx bx-current-location'></i>Fit to Route</button>
                    <button class="btn btn-outline" type="button" id="sample-route"><i class='bx bx-map'></i>Sample Path</button>
                </div>
                <div class="map-summary">
                    <h3>Waypoint Summary</h3>
                    <p style="margin:0 0 8px;color:var(--muted);" id="route-distance">Select map points to generate a route.</p>
                    <ul class="waypoints" id="waypoint-list"></ul>
                </div>
            </div>
            <div class="panel">
                <h2 id="route-form" style="margin-top:0;">Route Details</h2>
                <p style="color:var(--muted); margin-top:4px;">Complete operational metadata for the route you are mapping. This information drives analytics and reporting.</p>
                <form method="POST" autocomplete="off" id="route-details-form">
                    <div class="form-group">
                        <label for="route_name">Stress *</label>
                        <input type="text" class="input" id="route_name" name="route_name" placeholder="e.g., Zone 3 Night Patrol" required>
                    </div>
                    <div class="form-group">
                        <label for="zone"><Address></Address></label>
                        <input type="text" class="input" id="zone" name="zone" placeholder="Barangay 12 - Coastal Strip">
                    </div>
                    <div class="form-group">
                        <label for="patrol_type">Patrol Type</label>
                        <select class="select" id="patrol_type" name="patrol_type">
                            <option value="">Select type</option>
                            <option value="Foot">Foot Patrol</option>
                            <option value="Vehicle">Vehicle Patrol</option>
                            <option value="Motorcycle">Motorcycle</option>
                            <option value="Bicycle">Bicycle</option>
                            <option value="Mixed">Mixed Coverage</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="priority">Priority Level</label>
                        <select class="select" id="priority" name="priority">
                            <option value="Normal">Normal</option>
                            <option value="High">High</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Operational Status</label>
                        <select class="select" id="status" name="status">
                            <option value="Planned">Planned</option>
                            <option value="Active">Active</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Complete">Complete</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="schedule_window">Schedule Window</label>
                        <input type="text" class="input" id="schedule_window" name="schedule_window" placeholder="Daily 18:00 - 23:00">
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes / Alerts</label>
                        <textarea id="notes" name="notes" placeholder="Add checkpoints, critical context, or reminders."></textarea>
                    </div>
                    <input type="hidden" name="coordinates" id="coordinates-field">
                    <button type="submit" class="btn btn-primary btn-disabled" id="save-route-btn" style="width:100%; justify-content:center;"><i class='bx bx-cloud-upload'></i>Save Route</button>
                    <p style="font-size:13px;color:var(--muted);text-align:center;margin-top:8px;" id="form-hint">Add at least two waypoints on the map and provide a route name to enable saving.</p>
                </form>
            </div>
        </div>

        <div class="panel table-panel">
            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                <div>
                    <h2 style="margin:0;">Route Library</h2>
                    <p style="color:var(--muted); margin:4px 0 0;">Select a saved route to visualize it on the map, or export the list for reporting.</p>
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <?php if (count($preparedRoutes) === 0): ?>
                        <a href="?generate_samples=1" class="btn btn-primary" onclick="return confirm('This will create 5 sample routes for testing. Continue?');"><i class='bx bx-plus-circle'></i>Generate Sample Routes</a>
                    <?php endif; ?>
                    <button class="btn btn-outline" type="button" id="export-routes"><i class='bx bx-download'></i>Export CSV</button>
                </div>
            </div>
            <table class="routes-table">
                <thead>
                    <tr>
                        <th>Route Name</th>
                        <th>Zone</th>
                        <th>Patrol Type</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Length (km)</th>
                        <th>Waypoints</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preparedRoutes as $index => $route): ?>
                        <tr data-route-index="<?php echo $index; ?>">
                            <td><?php echo htmlspecialchars($route['route_name']); ?></td>
                            <td><?php echo htmlspecialchars($route['zone']); ?></td>
                            <td><?php echo htmlspecialchars($route['patrol_type']); ?></td>
                            <td><span class="badge <?php echo strtolower($route['priority']); ?>"><?php echo htmlspecialchars($route['priority']); ?></span></td>
                            <td>
                                <?php
                                    $statusClass = 'status-planned';
                                    $statusValue = strtolower($route['status']);
                                    if ($statusValue === 'active' || $statusValue === 'ongoing') {
                                        $statusClass = 'status-active';
                                    } elseif ($statusValue === 'complete') {
                                        $statusClass = 'status-complete';
                                    }
                                ?>
                                <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($route['status']); ?></span>
                            </td>
                            <td><?php echo $route['distance']; ?></td>
                            <td><?php echo $route['points']; ?></td>
                            <td><?php echo $route['created_at'] ? date('M d, Y', strtotime($route['created_at'])) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (!count($preparedRoutes)): ?>
                <p style="margin-top:16px; color:var(--muted);">No saved routes yet. Plot waypoints on the map and use the form above to build your first patrol path.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Dynamic Leaflet loading with multiple CDN fallbacks
        (function() {
            const cdnSources = [
                'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js'
            ];
            
            let currentIndex = 0;
            
            function loadLeaflet() {
                if (typeof L !== 'undefined') {
                    // Leaflet already loaded
                    console.log('Leaflet loaded successfully');
                    initializeMapApp();
                    return;
                }
                
                if (currentIndex >= cdnSources.length) {
                    // All CDNs failed
                    console.error('All CDN sources failed to load Leaflet');
                    const mapContainer = document.getElementById('route-map');
                    if (mapContainer) {
                        mapContainer.innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted);"><strong>Map library failed to load</strong><br><br>All CDN sources failed. Possible causes:<br>• No internet connection<br>• Firewall blocking CDN requests<br>• Network restrictions<br><br>Please check your connection and refresh the page, or contact your network administrator.</div>';
                    }
                    return;
                }
                
                console.log('Attempting to load Leaflet from:', cdnSources[currentIndex]);
                const script = document.createElement('script');
                script.src = cdnSources[currentIndex];
                script.crossOrigin = 'anonymous';
                script.onload = function() {
                    console.log('Script loaded, checking for L object...');
                    // Wait a bit for L to be defined
                    setTimeout(function() {
                        if (typeof L !== 'undefined') {
                            console.log('Leaflet (L) object found, initializing map...');
                            initializeMapApp();
                        } else {
                            console.warn('L object not found, trying next CDN...');
                            currentIndex++;
                            loadLeaflet();
                        }
                    }, 200);
                };
                script.onerror = function() {
                    console.error('Failed to load from:', cdnSources[currentIndex]);
                    currentIndex++;
                    loadLeaflet();
                };
                document.head.appendChild(script);
            }
            
            // Start loading when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', loadLeaflet);
            } else {
                loadLeaflet();
            }
        })();
        
        // Main map initialization function
        function initializeMapApp() {
            // Check if Leaflet is loaded
            if (typeof L === 'undefined') {
                console.error('Leaflet not available');
                return;
            }

            // Initialize map with error handling
            let map;
            const mapContainer = document.getElementById('route-map');
            
            if (!mapContainer) {
                console.error('Map container not found');
                return;
            }

            try {
                map = L.map('route-map', {
                    center: [14.5995, 120.9842], // Default to Manila, Philippines
                    zoom: 13,
                    zoomControl: true,
                    attributionControl: true
                });
                
                // Add OpenStreetMap tile layer
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    tileSize: 256,
                    zoomOffset: 0,
                    errorTileUrl: 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
                }).addTo(map);
                
                // Ensure map renders properly after initialization
                setTimeout(() => {
                    if (map) {
                        map.invalidateSize();
                    }
                }, 200);
            } catch (error) {
                console.error('Map initialization error:', error);
                mapContainer.innerHTML = '<div style="padding:20px;text-align:center;color:var(--muted);">Map failed to load: ' + error.message + '. Please refresh the page.</div>';
                map = null;
            }
            
            // Only proceed if map was initialized successfully
            if (!map) {
                console.error('Map not initialized - functionality disabled');
                return;
            }
        
            // Initialize route mapping functionality
            let currentPoints = [];
            let currentPolyline = L.polyline([], { color: '#2563eb', weight: 5, opacity: 0.85 }).addTo(map);
            const waypointList = document.getElementById('waypoint-list');
            const distanceLabel = document.getElementById('route-distance');
            const hiddenField = document.getElementById('coordinates-field');
            const routeNameInput = document.getElementById('route_name');
            const saveButton = document.getElementById('save-route-btn');
            const formHint = document.getElementById('form-hint');
            const routeForm = document.getElementById('route-details-form');
            const preparedRoutes = <?php echo $routesForJs ?: '[]'; ?>;
            const savedRouteLayer = L.layerGroup().addTo(map);
            const waypointMarkers = L.layerGroup().addTo(map);
            let waypointMarkerObjs = [];
            const searchLayer = L.layerGroup().addTo(map);
            const flashAlert = document.querySelector('.alert-error');

        function computeDistance(points) {
            if (points.length < 2) return 0;
            let total = 0;
            const R = 6371;
            for (let i = 1; i < points.length; i++) {
                const [lat1, lon1] = points[i - 1].map(v => v * Math.PI / 180);
                const [lat2, lon2] = points[i].map(v => v * Math.PI / 180);
                const dlat = lat2 - lat1;
                const dlon = lon2 - lon1;
                const a = Math.sin(dlat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dlon / 2) ** 2;
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                total += R * c;
            }
            return total.toFixed(2);
        }

        function updateSummary() {
            waypointList.innerHTML = '';
            currentPoints.forEach((point, index) => {
                const li = document.createElement('li');
                li.textContent = `Waypoint ${index + 1}: ${point[0].toFixed(5)}, ${point[1].toFixed(5)}`;
                waypointList.appendChild(li);
            });

            const totalKm = computeDistance(currentPoints);
            distanceLabel.textContent = currentPoints.length >= 2
                ? `${currentPoints.length} waypoints • ${totalKm} km`
                : 'Select map points to generate a route.';

            hiddenField.value = JSON.stringify(currentPoints);
            updateButtonState();
        }

        function updateButtonState() {
            const hasName = routeNameInput.value.trim().length > 0;
            const hasWaypoints = currentPoints.length >= 2;
            const isValid = hasName && hasWaypoints;
            saveButton.classList.toggle('btn-disabled', !isValid);
            formHint.textContent = isValid
                ? 'Ready to save — click the button above.'
                : 'Add at least two waypoints on the map and provide a route name to enable saving.';
            if (isValid && flashAlert) {
                flashAlert.style.display = 'none';
            }
        }

        if (map) {
            map.on('click', (event) => {
                currentPoints.push([event.latlng.lat, event.latlng.lng]);
                const mk = L.circleMarker(event.latlng, { radius: 6, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.9 }).addTo(waypointMarkers);
                mk.bindTooltip('Waypoint ' + currentPoints.length, { permanent: true, direction: 'top', offset: [0, -8] });
                waypointMarkerObjs.push(mk);
                if (currentPolyline) currentPolyline.setLatLngs(currentPoints);
                updateSummary();
            });
        }

        document.getElementById('undo-point').addEventListener('click', () => {
            if (!map) return;
            currentPoints.pop();
            const last = waypointMarkerObjs.pop();
            if (last && waypointMarkers) waypointMarkers.removeLayer(last);
            if (currentPolyline) currentPolyline.setLatLngs(currentPoints);
            updateSummary();
        });

        document.getElementById('clear-route').addEventListener('click', () => {
            if (!map) return;
            currentPoints = [];
            if (waypointMarkers) waypointMarkers.clearLayers();
            waypointMarkerObjs = [];
            if (currentPolyline) currentPolyline.setLatLngs(currentPoints);
            updateSummary();
        });

        document.getElementById('finish-route').addEventListener('click', () => {
            if (!map || !currentPoints.length) return;
            map.fitBounds(L.latLngBounds(currentPoints), { padding: [20, 20] });
        });

        document.getElementById('sample-route').addEventListener('click', () => {
            if (!map) return;
            const center = map.getCenter();
            const lat = center.lat;
            const lng = center.lng;
            currentPoints = [
                [lat + 0.002, lng - 0.002],
                [lat + 0.001, lng + 0.001],
                [lat - 0.0015, lng + 0.002]
            ];
            if (waypointMarkers) waypointMarkers.clearLayers();
            waypointMarkerObjs = currentPoints.map((pt, idx) => {
                const mk = L.circleMarker(pt, { radius: 6, color: '#2563eb', fillColor: '#2563eb', fillOpacity: 0.9 }).addTo(waypointMarkers);
                mk.bindTooltip('Waypoint ' + (idx + 1), { permanent: true, direction: 'top', offset: [0, -8] });
                return mk;
            });
            if (currentPolyline) currentPolyline.setLatLngs(currentPoints);
            map.fitBounds(L.latLngBounds(currentPoints), { padding: [20, 20] });
            if (!routeNameInput.value.trim()) {
                const stamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                routeNameInput.value = `Sample Route ${stamp}`;
            }
            updateSummary();
            updateButtonState();
        });

        // Locate me
        if (map) {
            document.getElementById('locate-me').addEventListener('click', () => {
                if (!map) return;
                map.locate({ setView: true, maxZoom: 17 });
            });
            map.on('locationfound', (e) => {
                if (searchLayer) searchLayer.clearLayers();
                L.circleMarker(e.latlng, { radius: 7, color: '#10b981', fillColor: '#10b981', fillOpacity: 0.9 })
                    .addTo(searchLayer)
                    .bindPopup('You are here')
                    .openPopup();
            });
            map.on('locationerror', () => alert('Location access denied.'));
        }

        // Search functionality with improved error handling
        const searchInput = document.getElementById('search-query');
        const searchButton = document.getElementById('search-go');
        
        async function performSearch() {
            if (!map) {
                alert('Map is not available. Please wait for the map to load.');
                return;
            }
            
            const q = searchInput.value.trim();
            if (!q) {
                alert('Please enter a place name or address to search.');
                return;
            }
            
            // Show loading state
            const originalButtonText = searchButton.innerHTML;
            searchButton.disabled = true;
            searchButton.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Searching...';
            
            try {
                const searchUrl = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(q) + '&addressdetails=1';
                const resp = await fetch(searchUrl);
                
                if (!resp.ok) {
                    throw new Error(`HTTP ${resp.status}: ${resp.statusText}`);
                }
                
                const data = await resp.json();
                
                if (data && data.length > 0) {
                    // Use first result
                    const result = data[0];
                    const lat = parseFloat(result.lat);
                    const lon = parseFloat(result.lon);
                    
                    if (isNaN(lat) || isNaN(lon)) {
                        throw new Error('Invalid coordinates received');
                    }
                    
                    // Clear previous search results
                    if (searchLayer) searchLayer.clearLayers();
                    
                    // Add marker for the location (using default icon for reliability)
                    const marker = L.marker([lat, lon]).addTo(searchLayer);
                    
                    const displayName = result.display_name || result.name || q;
                    marker.bindPopup(`<strong>${displayName}</strong><br>Lat: ${lat.toFixed(6)}, Lon: ${lon.toFixed(6)}`).openPopup();
                    
                    // Zoom to location
                    map.setView([lat, lon], 15);
                    
                    // If multiple results, show info
                    if (data.length > 1) {
                        console.log(`Found ${data.length} results, showing first: ${displayName}`);
                    }
                } else {
                    alert(`No results found for "${q}".\n\nPlease try:\n• A more specific location name\n• Include city or country\n• Check spelling`);
                }
            } catch (e) {
                console.error('Search error:', e);
                let fallbackUsed = false;
                try {
                    const photonUrl = 'https://photon.komoot.io/api/?q=' + encodeURIComponent(q) + '&limit=5';
                    const photonResp = await fetch(photonUrl);
                    if (photonResp.ok) {
                        const photonData = await photonResp.json();
                        if (photonData && photonData.features && photonData.features.length > 0) {
                            const feat = photonData.features[0];
                            const coords = feat.geometry && feat.geometry.coordinates;
                            if (Array.isArray(coords) && coords.length >= 2) {
                                const lon = parseFloat(coords[0]);
                                const lat = parseFloat(coords[1]);
                                if (!isNaN(lat) && !isNaN(lon)) {
                                    if (searchLayer) searchLayer.clearLayers();
                                    const marker = L.marker([lat, lon]).addTo(searchLayer);
                                    const props = feat.properties || {};
                                    const displayName = props.name || props.street || props.city || q;
                                    marker.bindPopup(`<strong>${displayName}</strong><br>Lat: ${lat.toFixed(6)}, Lon: ${lon.toFixed(6)}`).openPopup();
                                    map.setView([lat, lon], 15);
                                    fallbackUsed = true;
                                }
                            }
                        }
                    }
                } catch (ex) {
                    console.error('Fallback search error:', ex);
                }
                if (!fallbackUsed) {
                    let errorMsg = 'Search failed. ';
                    if (e.message && (e.message.includes('Failed to fetch') || e.message.includes('NetworkError'))) {
                        errorMsg += 'Network error. Please check your internet connection.';
                    } else if (e.message && (e.message.includes('429') || e.message.includes('rate limit'))) {
                        errorMsg += 'Too many requests. Please wait a moment and try again.';
                    } else if (e.message && e.message.includes('HTTP')) {
                        errorMsg += e.message;
                    } else {
                        errorMsg += 'Please try again or use a different search term.';
                    }
                    alert(errorMsg);
                }
            } finally {
                // Restore button state
                searchButton.disabled = false;
                searchButton.innerHTML = originalButtonText;
            }
        }
        
        // Search button click
        searchButton.addEventListener('click', performSearch);
        
        // Search on Enter key
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });

        routeNameInput.addEventListener('input', updateButtonState);

        routeForm.addEventListener('submit', (event) => {
            if (saveButton.classList.contains('btn-disabled')) {
                event.preventDefault();
                formHint.textContent = 'Please complete the required fields before saving.';
            }
        });

        function renderSavedRoute(index) {
            if (!map) return;
            const selected = preparedRoutes[index];
            if (!selected || !selected.coordinates.length) {
                alert('This route has no stored geometry yet.');
                return;
            }
            if (savedRouteLayer) savedRouteLayer.clearLayers();
            L.polyline(selected.coordinates, {
                color: '#10b981',
                weight: 4,
                dashArray: '10 6'
            }).addTo(savedRouteLayer);
            map.fitBounds(L.latLngBounds(selected.coordinates), { padding: [24, 24] });
        }

        document.querySelectorAll('tr[data-route-index]').forEach(row => {
            row.addEventListener('click', () => {
                renderSavedRoute(row.getAttribute('data-route-index'));
            });
        });

        document.getElementById('export-routes').addEventListener('click', () => {
            if (!preparedRoutes || !preparedRoutes.length) {
                alert('No data available for export.\n\nPlease:\n• Create routes using the map builder above\n• Or click "Generate Sample Routes" to create test data');
                return;
            }

            try {
                // Escape CSV values properly
                function escapeCsvValue(value) {
                    if (value === null || value === undefined) return '""';
                    const str = String(value);
                    // If contains comma, newline, or quote, wrap in quotes and escape quotes
                    if (str.includes(',') || str.includes('\n') || str.includes('"')) {
                        return `"${str.replace(/"/g, '""')}"`;
                    }
                    return str;
                }

                const headers = ['Route Name', 'Zone', 'Patrol Type', 'Priority', 'Status', 'Distance (km)', 'Waypoints', 'Schedule', 'Notes', 'Created Date'];
                const rows = preparedRoutes.map(route => [
                    escapeCsvValue(route.route_name || ''),
                    escapeCsvValue(route.zone || ''),
                    escapeCsvValue(route.patrol_type || ''),
                    escapeCsvValue(route.priority || ''),
                    escapeCsvValue(route.status || ''),
                    route.distance || '0',
                    route.points || '0',
                    escapeCsvValue(route.schedule_window || ''),
                    escapeCsvValue(route.notes || ''),
                    escapeCsvValue(route.created_at || '')
                ]);

                const csv = headers.join(',') + '\n' + rows.map(r => r.join(',')).join('\n');
                const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' }); // BOM for Excel compatibility
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                const timestamp = new Date().toISOString().split('T')[0];
                link.download = `route-mapping-export-${timestamp}.csv`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Clean up the object URL after a delay
                setTimeout(() => URL.revokeObjectURL(link.href), 100);
            } catch (error) {
                console.error('Export error:', error);
                alert('Failed to export data. Please try again or contact support if the issue persists.');
            }
        });

            updateSummary();
        } // End of initializeMapApp
    </script>
</body>
</html>
