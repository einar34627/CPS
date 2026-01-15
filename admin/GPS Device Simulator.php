<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

// Handle device creation
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_device') {
        $unitId = trim($_POST['unit_id'] ?? '');
        $callsign = trim($_POST['callsign'] ?? '');
        $assignment = trim($_POST['assignment'] ?? '');
        $latitude = floatval($_POST['latitude'] ?? 14.42);
        $longitude = floatval($_POST['longitude'] ?? 120.97);
        $status = $_POST['status'] ?? 'On Patrol';
        
        if (empty($unitId) || empty($callsign)) {
            $message = 'Unit ID and Callsign are required';
            $messageType = 'error';
        } else {
            try {
                // Check if device exists
                $checkStmt = $pdo->prepare("SELECT unit_id FROM gps_units WHERE unit_id = ?");
                $checkStmt->execute([$unitId]);
                if ($checkStmt->fetch()) {
                    $message = 'Device with this Unit ID already exists';
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO gps_units (unit_id, callsign, assignment, latitude, longitude, status, speed, battery, distance_today)
                        VALUES (?, ?, ?, ?, ?, ?, 0, 100, 0)
                    ");
                    $stmt->execute([$unitId, $callsign, $assignment, $latitude, $longitude, $status]);
                    $message = 'GPS device created successfully!';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($_POST['action'] === 'simulate_update') {
        // Simulate GPS update
        $unitId = $_POST['unit_id'] ?? '';
        if ($unitId) {
            try {
                // Get current position
                $stmt = $pdo->prepare("SELECT latitude, longitude FROM gps_units WHERE unit_id = ?");
                $stmt->execute([$unitId]);
                $device = $stmt->fetch();
                
                if ($device) {
                    // Simulate small movement (random walk)
                    $latOffset = (rand(-100, 100) / 10000); // ~0.01 degree = ~1km
                    $lngOffset = (rand(-100, 100) / 10000);
                    
                    $newLat = $device['latitude'] + $latOffset;
                    $newLng = $device['longitude'] + $lngOffset;
                    $speed = rand(0, 60);
                    $battery = max(0, min(100, rand(20, 100)));
                    
                    $updateStmt = $pdo->prepare("
                        UPDATE gps_units 
                        SET latitude = ?, longitude = ?, speed = ?, battery = ?, last_ping = NOW()
                        WHERE unit_id = ?
                    ");
                    $updateStmt->execute([$newLat, $newLng, $speed, $battery, $unitId]);
                    
                    // Record in history
                    $historyStmt = $pdo->prepare("
                        INSERT INTO gps_history (unit_id, latitude, longitude, speed)
                        VALUES (?, ?, ?, ?)
                    ");
                    $historyStmt->execute([$unitId, $newLat, $newLng, $speed]);
                    
                    $message = "GPS update simulated for {$unitId}";
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Fetch all devices
try {
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
            last_ping
        FROM gps_units
        WHERE is_active = 1
        ORDER BY callsign
    ");
    $devices = $stmt->fetchAll();
} catch (PDOException $e) {
    $devices = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Device Simulator | CPAS</title>
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
            max-width: 1200px;
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

        .message {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
        }

        .message.success {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .message.error {
            background: rgba(220, 38, 38, 0.15);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .card {
            background: var(--card);
            border-radius: 24px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.04);
            margin-bottom: 24px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 10px 12px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .devices-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .device-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.2s ease;
        }

        .device-card:hover {
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.1);
        }

        .device-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 16px;
        }

        .device-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .device-id {
            font-size: 12px;
            color: var(--muted);
            margin-top: 4px;
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

        .device-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .info-label {
            color: var(--muted);
        }

        .info-value {
            font-weight: 600;
        }

        .device-actions {
            display: flex;
            gap: 8px;
        }

        .action-button {
            flex: 1;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .action-button.simulate {
            background: var(--primary);
            color: #fff;
        }

        .action-button.simulate:hover {
            filter: brightness(0.95);
        }

        .action-button.view {
            background: rgba(148, 163, 184, 0.15);
            color: #475569;
        }

        .action-button.view:hover {
            background: rgba(148, 163, 184, 0.25);
        }

        .auto-simulate {
            background: rgba(34, 197, 94, 0.15);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .auto-simulate.active {
            background: var(--success);
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">GPS Device Simulator</h1>
                <p class="page-subtitle">
                    Create and simulate GPS tracking devices for testing
                </p>
            </div>
            <div class="header-actions">
                <a class="ghost-button" href="GPS%20Tracking.php">
                    <i class='bx bx-map'></i>
                    View Map
                </a>
                <a class="ghost-button" href="GPS%20Device%20Management.php">
                    <i class='bx bx-devices'></i>
                    Manage Devices
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 style="margin-top:0;">Create New GPS Device</h2>
            <form method="POST">
                <input type="hidden" name="action" value="create_device">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="unit_id">Unit ID *</label>
                        <input type="text" id="unit_id" name="unit_id" placeholder="UNIT-001" required>
                    </div>
                    <div class="form-group">
                        <label for="vehicle_type">Vehicle Type</label>
                        <select id="vehicle_type">
                            <option value="">Select vehicle</option>
                            <option>Van</option>
                            <option>Taxi</option>
                            <option>Police car</option>
                            <option>Bus</option>
                            <option>Ambulance</option>
                            <option>Jeep</option>
                            <option>Jeepney</option>
                            <option>Sedan</option>
                            <option>Hatchback</option>
                            <option>SUV</option>
                            <option>Pickup truck</option>
                            <option>Minivan</option>
                            <option>Sports car</option>
                            <option>Electric car</option>
                            <option>Police SUV</option>
                            <option>Taxi Sedan</option>
                            <option>Delivery van</option>
                            <option>Skateboard</option>
                            <option>Baby carriage / Pram</option>
                            <option>Bicycle</option>
                            <option>Road bike</option>
                            <option>Hybrid bike</option>
                            <option>BMX</option>
                            <option>E-bike</option>
                            <option>Cargo bike</option>
                            <option>Tricycle</option>
                            <option>Mountain bike</option>
                            <option>Scooter</option>
                            <option>Motor scooter</option>
                            <option>Motorcycle</option>
                            <option>Dirt bike</option>
                            <option>Fire engine</option>
                            <option>Crane</option>
                            <option>Forklift</option>
                            <option>Tractor</option>
                            <option>Recycling truck</option>
                            <option>Cement mixer</option>
                            <option>Dump truck</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="callsign">Callsign *</label>
                        <input type="text" id="callsign" name="callsign" placeholder="Alpha One" required>
                    </div>
                    <div class="form-group">
                        <label for="assignment">Assignment</label>
                        <input type="text" id="assignment" name="assignment" placeholder="Zone 1 - Coastal Road">
                    </div>
                    <div class="form-group">
                        <label for="latitude">Latitude</label>
                        <input type="number" step="0.000001" id="latitude" name="latitude" value="14.4231" placeholder="14.4231">
                    </div>
                    <div class="form-group">
                        <label for="longitude">Longitude</label>
                        <input type="number" step="0.000001" id="longitude" name="longitude" value="120.9724" placeholder="120.9724">
                    </div>
                    <div class="form-group">
                        <label for="status">Initial Status</label>
                        <select id="status" name="status">
                            <option value="On Patrol">On Patrol</option>
                            <option value="Responding">Responding</option>
                            <option value="Stationary">Stationary</option>
                            <option value="Needs Assistance">Needs Assistance</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="primary-button">
                        <i class='bx bx-plus'></i>
                        Create Device
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Simulate GPS Updates</h2>
            <p style="color:var(--muted); margin-bottom:20px;">
                Click "Simulate Update" on any device to generate a random GPS position update, or enable auto-simulation for continuous updates.
            </p>
            
            <?php if (empty($devices)): ?>
                <div style="text-align:center; padding:40px; color:var(--muted);">
                    <i class='bx bx-devices' style="font-size:48px; margin-bottom:12px; opacity:0.5;"></i>
                    <p>No GPS devices found. Create a device above to start simulating.</p>
                </div>
            <?php else: ?>
                <div class="devices-grid">
                    <?php foreach ($devices as $device): ?>
                        <div class="device-card">
                            <div class="device-header">
                                <div>
                                    <h3 class="device-title"><?php echo htmlspecialchars($device['callsign']); ?></h3>
                                    <div class="device-id"><?php echo htmlspecialchars($device['unit_id']); ?></div>
                                </div>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $device['status'])); ?>">
                                    <?php echo htmlspecialchars($device['status']); ?>
                                </span>
                            </div>
                            <div class="device-info">
                                <div class="info-row">
                                    <span class="info-label">Location</span>
                                    <span class="info-value" style="font-size:12px;">
                                        <?php echo number_format($device['latitude'], 6); ?>, <?php echo number_format($device['longitude'], 6); ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Speed</span>
                                    <span class="info-value"><?php echo $device['speed']; ?> km/h</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Battery</span>
                                    <span class="info-value"><?php echo $device['battery']; ?>%</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Last Update</span>
                                    <span class="info-value" style="font-size:12px;">
                                        <?php echo date('H:i:s', strtotime($device['last_ping'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="device-actions">
                                <form method="POST" style="flex:1;">
                                    <input type="hidden" name="action" value="simulate_update">
                                    <input type="hidden" name="unit_id" value="<?php echo htmlspecialchars($device['unit_id']); ?>">
                                    <button type="submit" class="action-button simulate">
                                        <i class='bx bx-refresh'></i> Simulate
                                    </button>
                                </form>
                                <button type="button" class="action-button view" onclick="window.location.href='GPS Tracking.php'">
                                    <i class='bx bx-map'></i> View
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh page every 5 seconds to show updates
        setInterval(() => {
            const forms = document.querySelectorAll('form[method="POST"]');
            const hasActiveForm = Array.from(forms).some(form => {
                const action = form.querySelector('input[name="action"]')?.value;
                return action === 'simulate_update';
            });
            
            // Only auto-refresh if not submitting a form
            if (!document.querySelector('form:has(button[type="submit"]:focus)')) {
                // Check if we should refresh (not during form submission)
                const urlParams = new URLSearchParams(window.location.search);
                if (!urlParams.has('refreshing')) {
                    window.location.href = window.location.pathname + '?refreshing=1';
                }
            }
        }, 5000);
    </script>
    <script>
        (function(){
            var vehicleSelect = document.getElementById('vehicle_type');
            var callsignInput = document.getElementById('callsign');
            var assignmentInput = document.getElementById('assignment');
            var defaults = {
                'Van':'Transport Fleet',
                'Taxi':'City Service',
                'Police car':'Patrol',
                'Bus':'Transit Hub',
                'Ambulance':'Emergency Response',
                'Jeep':'Off-road Support',
                'Jeepney':'City Transport',
                'Sedan':'City Roads',
                'Hatchback':'Residential Streets',
                'SUV':'Patrol Support',
                'Pickup truck':'Utility Route',
                'Minivan':'Community Transport',
                'Sports car':'Highway Patrol',
                'Electric car':'Green Fleet',
                'Police SUV':'Patrol Unit',
                'Taxi Sedan':'City Service',
                'Delivery van':'Logistics Route',
                'Skateboard':'Recreation Area',
                'Baby carriage / Pram':'Community Park',
                'Bicycle':'Bike Lane',
                'Road bike':'Road Cycling Lane',
                'Hybrid bike':'Mixed Use Path',
                'BMX':'Recreation Park',
                'E-bike':'Eco Route',
                'Cargo bike':'Delivery Path',
                'Tricycle':'Local Transport',
                'Mountain bike':'Trailhead',
                'Scooter':'Downtown',
                'Motor scooter':'Downtown',
                'Motorcycle':'Rapid Response',
                'Dirt bike':'Off-road Trail',
                'Fire engine':'Station 1',
                'Crane':'Construction Site',
                'Forklift':'Warehouse',
                'Tractor':'Farm Road',
                'Recycling truck':'Collection Route',
                'Cement mixer':'Build Zone',
                'Dump truck':'Industrial Park'
            };
            if (vehicleSelect) {
                vehicleSelect.addEventListener('change', function(){
                    var v = vehicleSelect.value;
                    if (v) {
                        if (callsignInput) callsignInput.value = v;
                        if (assignmentInput && (!assignmentInput.value || defaults[v])) assignmentInput.value = defaults[v] || assignmentInput.value;
                    }
                });
            }
        })();
    </script>
</body>
</html>

