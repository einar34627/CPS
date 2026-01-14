<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

date_default_timezone_set('Asia/Manila');

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $unitId = trim($_POST['unit_id'] ?? '');
            $callsign = trim($_POST['callsign'] ?? '');
            $assignment = trim($_POST['assignment'] ?? '');
            $latitude = floatval($_POST['latitude'] ?? 0);
            $longitude = floatval($_POST['longitude'] ?? 0);
            $status = $_POST['status'] ?? 'On Patrol';
            
            if (empty($unitId) || empty($callsign)) {
                $message = 'Unit ID and Callsign are required';
                $messageType = 'error';
            } else {
                try {
                    if ($_POST['action'] === 'edit' && isset($_POST['editing_unit_id'])) {
                        $editingUnitId = $_POST['editing_unit_id'];
                        $stmt = $pdo->prepare("
                            UPDATE gps_units 
                            SET unit_id = ?, callsign = ?, assignment = ?, latitude = ?, longitude = ?, status = ?
                            WHERE unit_id = ?
                        ");
                        $stmt->execute([$unitId, $callsign, $assignment, $latitude, $longitude, $status, $editingUnitId]);
                        $message = 'GPS device updated successfully';
                        $messageType = 'success';
                    } else {
                        $checkStmt = $pdo->prepare("SELECT unit_id FROM gps_units WHERE unit_id = ?");
                        $checkStmt->execute([$unitId]);
                        if ($checkStmt->fetch()) {
                            $message = 'This unit ID already exists';
                            $messageType = 'error';
                        } else {
                            $stmt = $pdo->prepare("
                                INSERT INTO gps_units (unit_id, callsign, assignment, latitude, longitude, status)
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$unitId, $callsign, $assignment, $latitude, $longitude, $status]);
                            $message = 'GPS device added successfully';
                            $messageType = 'success';
                        }
                    }
                } catch (PDOException $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $unitId = $_POST['unit_id'] ?? '';
            try {
                $stmt = $pdo->prepare("UPDATE gps_units SET is_active = 0 WHERE unit_id = ?");
                $stmt->execute([$unitId]);
                $message = 'GPS device deleted successfully';
                $messageType = 'success';
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
            distance_today,
            last_ping,
            is_active,
            created_at
        FROM gps_units
        ORDER BY created_at DESC
    ");
    $devices = $stmt->fetchAll();
} catch (PDOException $e) {
    $devices = [];
    $message = 'Error fetching devices: ' . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Device Management | CPAS</title>
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


        .devices-table {
            width: 100%;
            border-collapse: collapse;
        }

        .devices-table th,
        .devices-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .devices-table th {
            font-weight: 600;
            color: var(--muted);
            font-size: 14px;
        }

        .devices-table td {
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

        .action-button {
            border: none;
            background: rgba(148, 163, 184, 0.15);
            color: #475569;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            margin-right: 6px;
            transition: 0.2s ease;
        }

        .action-button.edit {
            background: rgba(37, 99, 235, 0.15);
            color: var(--primary);
        }

        .action-button.delete {
            background: rgba(248, 113, 113, 0.15);
            color: var(--danger);
        }

        .action-button:hover {
            filter: brightness(0.95);
        }

        .modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            z-index: 1000;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #fff;
            border-radius: 24px;
            padding: 24px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.25);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            margin: 0;
            font-size: 24px;
        }

        .modal-close {
            border: none;
            background: transparent;
            font-size: 28px;
            cursor: pointer;
            color: var(--muted);
            line-height: 1;
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

        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }


        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10000;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            max-width: 400px;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast.success {
            border-color: var(--success);
            background: rgba(34, 197, 94, 0.1);
        }

        .toast.error {
            border-color: var(--danger);
            background: rgba(220, 38, 38, 0.1);
        }

        .toast-icon {
            font-size: 24px;
        }

        .toast.success .toast-icon {
            color: var(--success);
        }

        .toast.error .toast-icon {
            color: var(--danger);
        }

        .toast-message {
            flex: 1;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1 class="page-title">GPS Device Management</h1>
                <p class="page-subtitle">
                    Add, edit, and manage GPS tracking devices
                </p>
            </div>
            <div class="header-actions">
                <a class="ghost-button" href="GPS%20Tracking.php">
                    <i class='bx bx-map'></i>
                    View Tracking Map
                </a>
                <a class="ghost-button" href="admin_dashboard.php">
                    <i class='bx bxs-dashboard'></i>
                    Dashboard
                </a>
                <button type="button" class="primary-button" id="add-device-btn">
                    <i class='bx bx-plus'></i>
                    Add Device
                </button>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2 style="margin-top:0;">Registered GPS Devices</h2>
            <div style="overflow-x:auto;">
                <table class="devices-table">
                    <thead>
                        <tr>
                            <th>Unit ID</th>
                            <th>Callsign</th>
                            <th>Assignment</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Battery</th>
                            <th>Last Ping</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($devices)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:40px; color:var(--muted);">
                                    No GPS devices registered. Click "Add Device" to register one.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($devices as $device): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($device['unit_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($device['callsign']); ?></td>
                                    <td><?php echo htmlspecialchars($device['assignment'] ?? 'N/A'); ?></td>
                                    <td style="font-size:12px;">
                                        <?php echo number_format($device['latitude'], 6); ?>, <?php echo number_format($device['longitude'], 6); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $device['status'])); ?>">
                                            <?php echo htmlspecialchars($device['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $device['battery']; ?>%</td>
                                    <td><?php echo date('M d, Y H:i', strtotime($device['last_ping'])); ?></td>
                                    <td>
                                        <button class="action-button edit" onclick="editDevice('<?php echo htmlspecialchars($device['unit_id']); ?>')">
                                            Edit
                                        </button>
                                        <button class="action-button delete" onclick="deleteDevice('<?php echo htmlspecialchars($device['unit_id']); ?>')">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Device Modal -->
    <div class="modal" id="device-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modal-title">Add GPS Device</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="device-form" method="POST">
                <input type="hidden" name="action" id="form-action" value="add">
                <input type="hidden" name="editing_unit_id" id="editing-unit-id">
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
                    <div class="form-group full-width">
                        <label for="assignment">Assignment</label>
                        <input type="text" id="assignment" name="assignment" placeholder="Zone 1 - Coastal Road">
                    </div>
                    <div class="form-group">
                        <label for="latitude">Latitude *</label>
                        <input type="number" step="0.000001" id="latitude" name="latitude" placeholder="14.4231" required>
                    </div>
                    <div class="form-group">
                        <label for="longitude">Longitude *</label>
                        <input type="number" step="0.000001" id="longitude" name="longitude" placeholder="120.9724" required>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="On Patrol">On Patrol</option>
                            <option value="Responding">Responding</option>
                            <option value="Stationary">Stationary</option>
                            <option value="Needs Assistance">Needs Assistance</option>
                        </select>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="ghost-button" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="primary-button">Save Device</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const devices = <?php echo json_encode($devices, JSON_UNESCAPED_UNICODE); ?>;
        const modal = document.getElementById('device-modal');
        const form = document.getElementById('device-form');
        const modalTitle = document.getElementById('modal-title');
        const formAction = document.getElementById('form-action');
        const editingUnitId = document.getElementById('editing-unit-id');
        const vehicleSelect = document.getElementById('vehicle_type');
        const callsignInput = document.getElementById('callsign');
        const assignmentInput = document.getElementById('assignment');
        const defaults = {
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
                const v = vehicleSelect.value;
                if (v) {
                    if (callsignInput) callsignInput.value = v;
                    if (assignmentInput && (!assignmentInput.value || defaults[v])) assignmentInput.value = defaults[v] || assignmentInput.value;
                }
            });
        }

        document.getElementById('add-device-btn').addEventListener('click', () => {
            openModal();
        });

        function openModal(device = null) {
            if (device) {
                modalTitle.textContent = 'Edit GPS Device';
                formAction.value = 'edit';
                editingUnitId.value = device.unit_id;
                document.getElementById('unit_id').value = device.unit_id;
                document.getElementById('callsign').value = device.callsign;
                document.getElementById('assignment').value = device.assignment || '';
                document.getElementById('latitude').value = device.latitude;
                document.getElementById('longitude').value = device.longitude;
                document.getElementById('status').value = device.status;
                if (vehicleSelect) vehicleSelect.value = '';
            } else {
                modalTitle.textContent = 'Add GPS Device';
                formAction.value = 'add';
                editingUnitId.value = '';
                form.reset();
                if (vehicleSelect) vehicleSelect.value = '';
            }
            modal.classList.add('active');
        }

        function closeModal() {
            modal.classList.remove('active');
            form.reset();
        }

        function editDevice(unitId) {
            const device = devices.find(d => d.unit_id === unitId);
            if (device) {
                openModal(device);
            }
        }

        function deleteDevice(unitId) {
            if (confirm('Are you sure you want to delete this GPS device?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="unit_id" value="${unitId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        function showToast(message, type = 'success') {
            // Remove existing toast if any
            const existingToast = document.querySelector('.toast');
            if (existingToast) {
                existingToast.remove();
            }

            // Create new toast
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class='bx ${type === 'success' ? 'bx-check-circle' : 'bx-error-circle'}'></i>
                </div>
                <div class="toast-message">${message}</div>
            `;
            document.body.appendChild(toast);

            // Show toast
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);

            // Hide and remove toast after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 3000);
        }
    </script>
</body>
</html>

