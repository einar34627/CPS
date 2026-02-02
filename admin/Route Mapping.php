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
   <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/leaflet.heat/dist/leaflet-heat.js"></script>
<script src="https://unpkg.com/osmtogeojson@3.0.0/osmtogeojson.js"></script>

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
            max-width: 100%;
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
            margin-bottom: 60px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
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

        /* .panel {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border);
        }
         */
        #route-map {
            width: 65%;
            height: 700px; /* or 100% if inside a container with height */
            border-radius: 16px;
            margin: 50px;
            background: #e2e8f0;
            border: 1px solid var(--border);
            position: relative;
            
        }
        .row {
            align-items: center;
            justify-content: center;
            display: flex;
        }
        #route-map.leaflet-container {
            z-index: 1;
        }

        .map-tools {
            position: absolute;
            left: 16px;
            right: 16px;
            bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 10px;
            border-radius: 12px;
            background: rgba(255,255,255,0.85);
            backdrop-filter: saturate(180%) blur(8px);
            box-shadow: 0 6px 14px rgba(0,0,0,0.08);
            z-index: 2;
            margin-top: 50px;
        }

        .map-summary {
            margin-top: 12px;
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
    #route-map {
        height: 320px;
    }
    .map-tools {
        flex-direction: column;
            }
        }
        .legend {
        background:white;
        padding:10px;
        font-size:13px;
        line-height:1.4;
        box-shadow:0 0 8px rgba(0,0,0,.2);
        border-radius:6px;
    }
    </style>
</head>
<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1>Patrol Route Mapping</h1>
                <p>Design, annotate, and monitor patrol routes with live spatial context.</p>
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
           <div class="row">
            <div id="route-map">
            </div>
                <div id="search-info" class="alert" style="display:none;"></div>
            </div>
            <div class="map-tools " style="margin-top:35px;">
                <input type="text" class="input" id="search-query" placeholder="Search place or address..." style="flex:1; min-width:220px;">
                <button class="btn btn-outline" type="button" id="search-go"><i class='bx bx-search'></i>Search</button>
                <button class="btn btn-outline" id="toggle-traffic"> <i class='bx bx-traffic-cone'></i> Traffic </button>
                <button class="btn btn-outline" type="button" id="finish-route"><i class='bx bx-current-location'></i>Fit to Route</button>
                <button class="btn btn-outline" type="button" id="toggle-zoning"><i class='bx bx-map'></i>Zoning Map</button>
                <button class="btn btn-outline" type="button" id="fit-commonwealth"><i class='bx bx-map-pin'></i>Commonwealth</button>
                <button class="btn btn-outline" type="button" id="toggle-color"><i class='bx bx-palette'></i>Color Map</button>
            </div>
           

    </div>

  <!-- Required Leaflet CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />

<!-- Leaflet JS loader -->
<script>
(function() {
    const cdnSources = [
        'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js'
    ];
    let currentIndex = 0;

    function loadLeaflet() {
        if (typeof L !== 'undefined') {
            initializeMapApp();
            return;
        }
        if (currentIndex >= cdnSources.length) return;

        const script = document.createElement('script');
        script.src = cdnSources[currentIndex];
        script.crossOrigin = 'anonymous';
        script.onload = () => setTimeout(() => {
            if (typeof L !== 'undefined') initializeMapApp();
            else { currentIndex++; loadLeaflet(); }
        }, 200);
        script.onerror = () => { currentIndex++; loadLeaflet(); };
        document.head.appendChild(script);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadLeaflet);
    } else {
        loadLeaflet();
    }
})();
</script>


<script>
function initializeMapApp() {
    if (typeof L === 'undefined') return;

    const routes = <?php echo $routesForJs; ?>;
    const map = L.map('route-map', {
        center: [14.7005, 121.0865],
        zoom: 15,
        minZoom: 14,
        maxZoom: 19
    });
    // --- Live Traffic Layer (TomTom if key present, else fallback) ---
    const tomtomKey = 'MP8VA1V0iQN1fE0yZmyUmxQE7vfE04YK';
    let trafficLayer;
    if (tomtomKey) {
        trafficLayer = L.tileLayer(
            'https://api.tomtom.com/traffic/map/4/tile/flow/relative/{z}/{x}/{y}.png?key=' + tomtomKey,
            { attribution: '&copy; TomTom Traffic', opacity: 0.9 }
        ).addTo(map);
    } else {
        trafficLayer = L.tileLayer(
            'https://tile.memomaps.de/tilegen/traffic/{z}/{x}/{y}.png',
            { opacity: 0.8, attribution: '&copy; OpenStreetMap contributors' }
        ).addTo(map);
    }
    // --- Base Map ---
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap & CARTO',
        maxZoom: 19
    }).addTo(map);

    // --- Overpass Data: Buildings ---
    fetch('https://overpass-api.de/api/interpreter?data=[out:json];area["name"="Quezon City"];(way["building"](area););out body;')
        .then(res => res.json())
        .then(data => {
            const toGeoJSON = window.osmtogeojson || window.osmToGeoJSON;
            if (toGeoJSON) {
                L.geoJSON(toGeoJSON(data), { style: { color:'#555', weight:1, fillColor:'#bbb', fillOpacity:0.4 } }).addTo(map);
            }
        });

    // --- Layers ---
    const waterLayer = L.tileLayer('https://tiles.wmflabs.org/hikebike/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    });

    const roadsLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const transportLayer = L.tileLayer('https://tile.thunderforest.com/transport/{z}/{x}/{y}.png?apikey=YOUR_API_KEY', {
        attribution: '&copy; OpenStreetMap & Thunderforest'
    });

    // --- Overpass Data: Parks ---
    fetch('https://overpass-api.de/api/interpreter?data=[out:json];area["name"="Quezon City"];(way["leisure"="park"](area);relation["leisure"="park"](area););out body;')
        .then(res => res.json())
        .then(data => {
            const toGeoJSON = window.osmtogeojson || window.osmToGeoJSON;
            if (toGeoJSON) {
                L.geoJSON(toGeoJSON(data), { style: { color:'green', fillOpacity:0.2 } }).addTo(map);
            }
        });

    // --- Commonwealth QC Boundary ---
    const commonwealthGeoJSON = {
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": { "name": "Commonwealth" },
      "geometry": {
        "type": "Polygon",
        "coordinates": [
          [
            [121.083089, 14.709909], [121.0827319, 14.7086933], [121.082621, 14.7083025],
            [121.082477, 14.7078641], [121.0823004, 14.7073802], [121.0821198, 14.7069657],
            [121.0818147, 14.7059143], [121.0815905, 14.7051927], [121.0815014, 14.7048893],
            [121.0813675, 14.704569], [121.0812002, 14.7041845], [121.080837, 14.7041825],
            [121.0795816, 14.704164], [121.0777782, 14.7041727], [121.0771962, 14.7041883],
            [121.0770465, 14.7040385], [121.0767196, 14.7038143], [121.0764196, 14.7036652],
            [121.0762954, 14.7036316], [121.0756248, 14.7034504], [121.0753926, 14.703369],
            [121.0751716, 14.7032915], [121.0749029, 14.7031676], [121.0746963, 14.7030288],
            [121.0741843, 14.7030437], [121.0742259, 14.7028376], [121.0742487, 14.7027871],
            [121.0740021, 14.7025363], [121.0737081, 14.702249], [121.0737537, 14.7021699],
            [121.0734353, 14.7017435], [121.0728592, 14.7006421], [121.0728041, 14.7002749],
            [121.0727533, 14.6999815], [121.0727274, 14.699677], [121.0726916, 14.6994613],
            [121.0723715, 14.6986078], [121.07174, 14.696937], [121.0714422, 14.6962263],
            [121.0709099, 14.6948733], [121.0701216, 14.6936084], [121.0699625, 14.6934091],
            [121.0696031, 14.6928864], [121.075253, 14.6933259], [121.0773019, 14.6932403],
            [121.0792747, 14.6932472], [121.0807457, 14.693322], [121.0860302, 14.6932916],
            [121.0873239, 14.6932842], [121.0928632, 14.6932789], [121.0928967, 14.693922],
            [121.0929047, 14.6949282], [121.0945421, 14.6965876], [121.0945856, 14.6966323],
            [121.0939586, 14.6971992], [121.0923053, 14.6987895], [121.0913908, 14.6997168],
            [121.0910747, 14.6999553], [121.0908265, 14.700101], [121.0905641, 14.7002206],
            [121.0901125, 14.700319], [121.089683, 14.7003204], [121.0893063, 14.7002606],
            [121.0888875, 14.7001514], [121.0882377, 14.6999812], [121.0878347, 14.6998756],
            [121.0877497, 14.6999409], [121.0878517, 14.7000567], [121.0879446, 14.7001603],
            [121.0880872, 14.7003187], [121.0884299, 14.7007282], [121.0885158, 14.7008171],
            [121.0886613, 14.7008878], [121.0891231, 14.7010617], [121.0892205, 14.7011234],
            [121.0892798, 14.701238], [121.0893023, 14.7013282], [121.0893156, 14.7014093],
            [121.0893191, 14.7014306], [121.089328, 14.7015229], [121.089351, 14.7017641],
            [121.089355, 14.7018396], [121.0893579, 14.7019005], [121.0893599, 14.7019963],
            [121.0893598, 14.7020426], [121.0893603, 14.7021353], [121.089362, 14.7022724],
            [121.0893621, 14.7023804], [121.0893489, 14.7025146], [121.0893315, 14.7025806],
            [121.0892668, 14.7027123], [121.0891752, 14.7028696], [121.089117, 14.7029694],
            [121.0890545, 14.7030846], [121.0889751, 14.7032301], [121.0889519, 14.7032712],
            [121.088917, 14.7033333], [121.0888896, 14.7033806], [121.088729, 14.7036547],
            [121.0886155, 14.7038624], [121.0885581, 14.7039989], [121.0885088, 14.704127],
            [121.0884541, 14.7043004], [121.0884169, 14.7044326], [121.0883021, 14.7049157],
            [121.08822, 14.7052547], [121.0881398, 14.7056096], [121.0880922, 14.7058778],
            [121.0880932, 14.7060099], [121.0881, 14.7060914], [121.0881116, 14.7061506],
            [121.0882223, 14.7063197], [121.0883329, 14.7064466], [121.0887232, 14.7068043],
            [121.0888375, 14.7069638], [121.0889178, 14.7071079], [121.0889315, 14.7071324],
            [121.0890059, 14.7072841], [121.0890453, 14.7073643], [121.0890824, 14.7074385],
            [121.0891248, 14.707481], [121.089173, 14.7075099], [121.0892284, 14.7075231],
            [121.0893762, 14.7075281], [121.0895685, 14.7075577], [121.0897098, 14.7076287],
            [121.0898469, 14.7077221], [121.0899289, 14.7077883], [121.0901239, 14.7079944],
            [121.0901656, 14.7080543], [121.0902204, 14.7081408], [121.0903137, 14.7082944],
            [121.090364, 14.7083773], [121.0904348, 14.7085047], [121.090456, 14.7085531],
            [121.0906045, 14.7088503], [121.0906691, 14.7089761], [121.0907357, 14.7091702],
            [121.0907465, 14.7092711], [121.0907371, 14.7096367], [121.0907377, 14.7097015],
            [121.0907306, 14.709957], [121.0907131, 14.710531], [121.0907082, 14.7106922],
            [121.0905796, 14.7108739], [121.0904498, 14.7110573], [121.0901977, 14.7114135],
            [121.090023, 14.7117509], [121.0898332, 14.7121533], [121.0871119, 14.7112189],
            [121.0869747, 14.7113735], [121.0868004, 14.7113584], [121.086672, 14.7113318],
            [121.0862659, 14.7112029], [121.0859762, 14.7110311], [121.0856651, 14.7109429],
            [121.0856973, 14.7110339], [121.0849946, 14.7105901], [121.0848906, 14.7103196],
            [121.0841055, 14.710054], [121.083089, 14.709909]
          ]
        ]
      }
    }
  ]
};
    const commonwealthZone = L.geoJSON(commonwealthGeoJSON, {
        style: { color:'#dc2626', weight:3, dashArray:'6 4', fillColor:'#fecaca', fillOpacity:0.3 }
    }).addTo(map);

    map.fitBounds(commonwealthZone.getBounds());
    map.setMaxBounds(commonwealthZone.getBounds());
    map.options.maxBoundsViscosity = 1;

    // --- Search Functionality ---
    const searchInput = document.getElementById('search-query');
    const searchBtn = document.getElementById('search-go');
    const searchLayer = L.layerGroup().addTo(map);
    const searchInfo = document.getElementById('search-info');

    // --- Predefined summaries for common locations in Commonwealth ---
const locationSummaries = {
    "Barangay Commonwealth Hall": "The seat of local government and community services in Barangay Commonwealth. Provides civic support, services, and hosts local events for residents.",

    "Barangay Commonwealth Multipurpose & Commercial Building": "A new community facility with commercial stalls and event spaces opened in 2025 to support local business and community activity. ",

    "Commonwealth Avenue": "The main thoroughfare running through the barangay, known for heavy traffic and connecting Commonwealth to other areas of Quezon City. Earned the nickname 'Killer Highway' due to its high accident rate.",

    "Commonwealth Market (Wet & Dry)": "A major local market serving Barangay Commonwealth for daily groceries, fresh produce, and household needs. Also includes the Litex Commonwealth market area with organized vendors.",

    "Litex Commonwealth Area": "A bustling market street area along Litex Road near Commonwealth Avenue, known for shops, food stalls, and vendor operations within the barangay.",

    "Commonwealth Elementary School": "A public elementary school on Commonwealth Avenue that serves students in and around Barangay Commonwealth.",

    "Benigno S. Aquino Jr. Elementary School": "A public elementary school located on Katuparan Street within Commonwealth, providing primary education to local children.",

    "Commonwealth High School": "A public secondary school in Commonwealth serving high school students, contributing to education in the community.",

    "Iglesia ni Cristo Capitol Locale": "A large neo‑Gothic chapel of the Iglesia ni Cristo located along Commonwealth Avenue, completed in 2014, with a large seating capacity and significant presence in the community.",

    "St. Peter Parish": "A local Roman Catholic parish serving Commonwealth residents with regular masses and community events.",

    "Kristong Hari Church": "A Christian church in the barangay serving worshippers and hosting community spiritual activities.",
    
    "Seventh‑Day Adventist Church": "A place of worship for the Seventh‑Day Adventist community within Commonwealth.",

    "Muslin Market": "A smaller local community market in Commonwealth where residents buy fresh produce and daily goods.",

    "Christian Market": "Another local market area within the barangay known for daily shopping and household needs.",

    "Commonwealth Heights Subdivision": "A major residential subdivision in Barangay Commonwealth and home to many local families.",

    "Ideal Subdivision": "A residential neighborhood in Commonwealth known for family homes and community life.",

    "Don Jose Subdivision": "A residential area within Commonwealth with houses and local streets.",

    "Doña Nicasia Puyat Subdivision": "A local subdivision providing housing and community networks for residents.",

    "Doña Carmen Subdivision": "A residential area within Commonwealth featuring neighborhood housing.",

    "Jordan Parks Home Subdivision": "Another residential neighborhood in Barangay Commonwealth, contributing to the community’s housing options.",
};

 
async function performSearch() {
    const q = searchInput.value.trim();
    if (!q) return;

    const photonUrl = `https://photon.komoot.io/api/?q=${encodeURIComponent(q)}&lat=14.7005&lon=121.0865&limit=5`;

    try {
        const res = await fetch(photonUrl);
        const data = await res.json();

        if (!data.features || !data.features.length) {
            alert('Location not found.');
            return;
        }

        const f = data.features[0];
        const [lon, lat] = f.geometry.coordinates;
        const p = f.properties || {};
        const ll = L.latLng(lat, lon);
        searchLayer.clearLayers();

        const title = p.name || p.street || q;
        const address = [p.street, p.city, p.state, p.country].filter(Boolean).join(', ');

        // Get summary info (fallback if none exists)
        const summary = locationSummaries[title] || "No additional information available for this location.";

        L.marker(ll)
            .addTo(searchLayer)
            .bindPopup(`
                <strong>${title}</strong><br>
                ${address}<br>
                <small>Lat: ${lat.toFixed(5)} | Lon: ${lon.toFixed(5)}</small><br>
                <em>${summary}</em>
            `)
            .openPopup();

        const inside = commonwealthZone.getBounds().contains(ll);
        map.setView(ll, inside ? 18 : 15);

        if (searchInfo) {
            searchInfo.style.display = 'flex';
            searchInfo.className = inside ? 'alert alert-success' : 'alert alert-error';
            searchInfo.innerHTML = `
                <i class='bx bx-map'></i>
                <span style="font-weight:600;">${title}</span>
                <span style="margin-left:auto;">${address || 'Commonwealth Area'} • ${inside ? 'Inside Commonwealth' : 'Outside Commonwealth'}</span>
                <br><small>${summary}</small>
            `;
        }

        if (!inside) {
            alert('Location found is outside Barangay Commonwealth.');
        }

    } catch (err) {
        console.error(err);
        alert('Search failed.');
    }
}

    searchBtn.addEventListener('click', performSearch);
    searchInput.addEventListener('keypress', e => {
        if (e.key === 'Enter') performSearch();
    });
    const trafficBtn = document.getElementById('toggle-traffic');
    let trafficOn = true;

    trafficBtn.addEventListener('click', () => {
        trafficOn = !trafficOn;
        if (trafficOn) {
            trafficLayer.addTo(map);
        } else {
            map.removeLayer(trafficLayer);
        }
    });

    


    const finishBtn = document.getElementById('finish-route');
    if (finishBtn) finishBtn.addEventListener('click', ()=> {
        if (routeLine && routePoints.length) map.fitBounds(routeLine.getBounds(), { padding:[20,20] });
    });

    const sampleBtn = document.getElementById('sample-route');
    if (sampleBtn) sampleBtn.addEventListener('click', ()=> { if (routes.length) drawRoute(routes[0].coordinates); });

    // --- Traffic Layer Toggle ---
    let trafficVisible = true;
    const toggleTrafficBtn = document.getElementById('toggle-traffic');
    if (toggleTrafficBtn) toggleTrafficBtn.addEventListener('click', ()=> {
        if (trafficVisible) map.removeLayer(trafficLayer);
        else map.addLayer(trafficLayer);
        trafficVisible = !trafficVisible;
    });

   // --- High-Risk Crime Areas ---
const highRiskAreas = [
    { name: "Commonwealth Ave & Litex", lat: 14.7015, lon: 121.0840, riskLevel: "High" },
    { name: "Commonwealth Ave & Don Antonio", lat: 14.7039, lon: 121.0860, riskLevel: "High" },
    { name: "Pedestrian Bridge near Fairview", lat: 14.7055, lon: 121.0885, riskLevel: "Medium" },
    { name: "Residential Alley, Sitio San Roque", lat: 14.7078, lon: 121.0855, riskLevel: "Medium" }
];

// Create a Layer Group for crime hotspots
const crimeLayer = L.layerGroup().addTo(map);

highRiskAreas.forEach(area => {
    // Circle color based on risk
    const color = area.riskLevel === "High" ? "#dc2626" : "#f59e0b";

    const circle = L.circle([area.lat, area.lon], {
        color: color,
        fillColor: color,
        fillOpacity: 0.5,
        radius: 80 // meters
    }).bindPopup(`
        <strong>${area.name}</strong><br>
        Risk Level: <b>${area.riskLevel}</b>
    `);

    crimeLayer.addLayer(circle);
});

// --- Optional: Toggle Crime Layer ---
const toggleCrimeBtn = document.getElementById('toggle-crime');
if (toggleCrimeBtn) toggleCrimeBtn.addEventListener('click', () => {
    if (map.hasLayer(crimeLayer)) map.removeLayer(crimeLayer);
    else map.addLayer(crimeLayer);
});

const zoningData = {
    "type": "FeatureCollection",
    "features": [
        {
            "type": "Feature",
            "properties": { 
                "zone": "R-3", 
                "name": "High Density Residential Zone" 
            },
            "geometry": {
                "type": "Polygon",
             "coordinates": [[
                [121.081560, 14.705206],
                [121.082818, 14.708973],
                [121.083019, 14.709711],
                [121.085027, 14.710554],
                [121.085713, 14.710938],
                [121.086991, 14.711357],
                [121.087077, 14.711208],
                [121.087116, 14.711212],
                [121.089827, 14.712090],
                [121.089913, 14.711915],
                [121.089935, 14.711920],
                [121.090262, 14.711312],
                [121.090523, 14.710917],
                [121.090564, 14.710857],
                [121.090633, 14.710798],
                [121.090664, 14.710783],
                [121.090718, 14.710780],
                [121.090719, 14.710652],
                [121.090765, 14.709223],
                [121.090771, 14.709189],
                [121.090576, 14.708728],
                [121.090129, 14.708033],
                [121.089919, 14.707778],
                [121.089570, 14.707568],
                [121.089165, 14.707509],
                [121.089047, 14.707445],
                [121.088939, 14.707190],
                [121.088727, 14.706857],
                [121.088383, 14.706553],
                [121.088090, 14.705934],
                [121.088126, 14.705486],
                [121.088472, 14.704284],
                [121.089291, 14.702563],
                [121.089228, 14.701159],
                [121.088558, 14.700834],
                [121.088045, 14.700285],
                [121.087732, 14.699975],
                [121.087699, 14.699950],
                [121.087463, 14.699911],
                [121.087205, 14.700007],
                [121.087183, 14.700107],
                [121.086666, 14.701497],
                [121.086345, 14.702064],
                [121.084683, 14.703921],
                [121.083478, 14.704681],
                [121.083121, 14.704820],
                [121.082104, 14.705066],
                [121.081732, 14.705165],
                [121.081547, 14.705194],
                [121.081560, 14.705206]
                ]]

            }
        },
        {
            "type": "Feature",
            "properties": { 
                "zone": "C-2", 
                "name": "Major Commercial Zone" 
            },
            "geometry": {
                "type": "Polygon",
                "coordinates": [[
                    [121.08146266186888, 14.70502032973792],
                    [121.08283352162017, 14.704578321656196],
                    [121.08253549890063, 14.704328506389237],
                    [121.08263363473358, 14.7037269825564],
                    [121.08263361313462, 14.70320839439809],
                    [121.08214688346864, 14.702869391212165],
                    [121.08201426130061, 14.702176802625651],
                    [121.08196120522693, 14.701638122136387],
                    [121.0819346668402, 14.700996835490386],
                    [121.08188162423696, 14.700894230836486],
                    [121.08185508420797, 14.700150338669117],
                    [121.08177550784082, 14.699483402356249],
                    [121.08125610409755, 14.69897745712169],
                    [121.08120602494309, 14.6981542581355],
                    [121.08113092020392, 14.697548965054079],
                    [121.08095569573008, 14.69711315656807],
                    [121.08090562481392, 14.696580497159466],
                    [121.08030488611743, 14.696653146440681],
                    [121.07890316421332, 14.696701594687172],
                    [121.07860279609444, 14.696750022240266],
                    [121.07752647648672, 14.696725820698274],
                    [121.0759996063364, 14.696943730947611],
                    [121.07562414606592, 14.69704057722639],
                    [121.07499837908576, 14.696992150762926],
                    [121.07492328689273, 14.697016362059172],
                    [121.07437261180733, 14.696967934067484],
                    [121.07359665826843, 14.697088984102361],
                    [121.0726204562682, 14.697210027613654],
                    [121.07194462105979, 14.69735528497783],
                    [121.07159418927495, 14.69730685349816],

                    [121.07176005252246, 14.69760472171269],
                    [121.07255654017236, 14.699419200011077],
                    [121.0730170996742, 14.700949509350854],
                    [121.07361927337217, 14.701920274063793],
                    [121.07379132316618, 14.702262352228114],
                    [121.07407329435864, 14.702470372685188],
                    [121.07410196943471, 14.702488863383977],
                    [121.07430269318151, 14.703001978331866],
                    [121.07468980429684, 14.702983486965337],
                    [121.07516933950532, 14.703267187106556],
                    [121.07616677668422, 14.70354754816688],
                    [121.07679763392132, 14.703811417752148],
                    [121.07718126339401, 14.704146751419708],
                    [121.08118521965115, 14.70417148894709],
                    [121.08122216182386, 14.704207221107538],
                    [121.08143813132077, 14.704897126800164]

                    ]]

            }
        },
        {
            "type": "Feature",
            "properties": { 
                "zone": "INSTITUTIONAL", 
                "name": "Institutional Zone" 
            },
            "geometry": {
                "type": "Polygon",
                "coordinates": [[        
                    [121.07157886096225, 14.697389349875621],
                    [121.0714954515572, 14.697166244134676],
                    [121.07077103539514, 14.695359608315764],
                    [121.07056250670865, 14.69440355413119],
                    [121.0696941925682, 14.693053512703557],
                    [121.07534088317519, 14.693378932729999],
                    [121.08388593885817, 14.693166506315592],
                    [121.08458022254854, 14.696336657609693]
                ]]

            }
        },
        {
            "type": "Feature",
            "properties": { 
                "zone": "C-1", 
                "name": "sample Zone" 
            },
            "geometry": {
                "type": "Polygon",
              "coordinates": [[
                                    [121.08388593885817, 14.693166506315592],
                [121.09280418139242, 14.69326652757271],
                [121.09288075931981, 14.694969856614843],
                [121.0945452868604, 14.696739459070985],
                [121.09124278359252, 14.699672619254418],
                [121.09051844872188, 14.700135749811508],
                [121.0903465722914, 14.700254500923808],
                [121.08999054179402, 14.700397002313867],
                [121.08754745066798, 14.699898247944745],
                [121.08693910771707, 14.699902871734135],
                [121.08636899865611, 14.701584141747206],
                [121.08577117894203, 14.702500443699405],
                [121.0829660191976, 14.704662187646846],
                [121.08205548471565, 14.70490238036893],
                [121.08262572011644, 14.704199591643455],
                [121.08255214411915, 14.703283297363749],
                [121.08223024147424, 14.703087583234895],
                [121.08188318571757, 14.701051806700905],
                [121.08170805339704, 14.699827493312066],
                [121.0812176845922, 14.698973720164991],
                [121.08112697664797, 14.697597477754526],
                [121.08104165988766, 14.697338904846092],
                [121.08092694567263, 14.696760330225501],
                [121.08458022254854, 14.696336657609693]
            ]]


            }
        }
    ]
};
// 1. Variable para sa Zoning Layer (naka-null sa simula)
let zoningLayer = null;
let isZoningVisible = false;

// 2. Ang Re-coded Zoning Style Function
function getZoningStyle(feature) {
    const zone = feature.properties.zone;
    let fillColor;

    // Kinopya ang color scheme base sa iyong reference legend
    switch (zone) {
        case 'R-2': fillColor = '#f3f56cff'; break; // Yellow
        case 'R-3': fillColor = '#ec4899'; break; // Orange
        case 'C-1': fillColor = '#fbbf24 '; break; // Blue
        case 'C-2': fillColor = '#5ee97cff' ; break; // Pink
        case 'INSTITUTIONAL': fillColor = '#f3f56cff '; break; // Blue/Violet
        case 'SOCIALIZED HOUSING': fillColor = '#a78bfa'; break; // Purple
        case 'SPECIAL URBAN': fillColor = '#8b5cf6'; break; // Deep Purple
        default: fillColor = '#94a3b8';
    }

    return {
        fillColor: fillColor,
        weight: 1.5,
        opacity: 1,
        color: 'white',
        fillOpacity: 0.6
    };
}

// 3. Button Click Event Listener
document.getElementById('toggle-zoning').addEventListener('click', function() {
    if (!isZoningVisible) {
        // Kung wala pa ang layer, i-create ito
        if (!zoningLayer) {
            zoningLayer = L.geoJSON(zoningData, { // Siguraduhin na 'zoningData' ang name ng Variable mo
                style: getZoningStyle,
                onEachFeature: function (feature, layer) {
                    layer.bindPopup(`<b>Zone:</b> ${feature.properties.zone}<br><b>Type:</b> ${feature.properties.name}`);
                }
            });
        }
        zoningLayer.addTo(map);
        this.innerText = "Hide Zoning Map";
        this.style.backgroundColor = "#feb2b2"; // Magpapalit ng kulay ang button
    } else {
        // Kung visible na, tanggalin sa map
        map.removeLayer(zoningLayer);
        this.innerText = "Show Zoning Map";
        this.style.backgroundColor = ""; // Balik sa default color
    }
    isZoningVisible = !isZoningVisible; // I-toggle ang state
});


// --- Update Legend ---
const legend = L.control({ position:'bottomright' });
legend.onAdd = () => {
    const div = L.DomUtil.create('div', 'legend');
    
    // Inline CSS para sa malinis na itsura ng mga color boxes
    const boxStyle = "display:inline-block; width:12px; height:12px; margin-right:5px; border:1px solid #000;";

    div.innerHTML = `
        <div style="background: white; padding: 10px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.2);">
            <b>Map Legend</b><br>
            <span style="color:#dc2626;">━━━</span> Commonwealth Boundary<br>
            <span style="color:#2563eb;">━━━</span> Commonwealth Ave<br>
            <span style="color:#1d4ed8;">━━━</span> Patrol Route<br>
            <span style="color:#dc2626;">●</span> High-Risk Crime Area<br>
            <span style="color:#f59e0b;">●</span> Medium-Risk Crime Area<br>
            <hr style="margin: 5px 0;">
            <b>Zoning Zones</b><br>
            <div style="margin-top:4px;">
                <span style="${boxStyle} background:#fef08a;"></span> R-2 Medium Density<br>
                <span style="${boxStyle} background:#fbbf24;"></span> R-3 High Density<br>
                <span style="${boxStyle} background:#ec4899;"></span> C-1/C-2 Commercial<br>
                <span style="${boxStyle} background:#6366f1;"></span> Institutional<br>
                <span style="${boxStyle} background:#a78bfa;"></span> Socialized Housing<br>
                <span style="${boxStyle} background:#8b5cf6;"></span> Special Urban Dev't<br>
            </div>
        </div>
    `;
    return div;
};

legend.addTo(map);
}

</script>


</body>
</html>
