<?php
// Token-based access for partners
$valid_tokens = [
    'embed_token_123' => [
        'partner' => 'Partner Name',
        'allowed_domains' => ['partner-domain.com'],
        'style' => 'default'
    ]
];

$token = $_GET['token'] ?? '';
if (!isset($valid_tokens[$token])) {
    die('Invalid access token');
}

// Check referrer if needed
if (!empty($valid_tokens[$token]['allowed_domains'])) {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $allowed = false;
    foreach ($valid_tokens[$token]['allowed_domains'] as $domain) {
        if (strpos($referer, $domain) !== false) {
            $allowed = true;
            break;
        }
    }
    if (!$allowed && $referer) {
        die('Domain not authorized');
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPAS Live GPS Map</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
        #map-container { width: 100%; height: 100vh; position: relative; }
        #map { width: 100%; height: 100%; }
        .map-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .unit-info {
            background: white;
            border-radius: 6px;
            padding: 8px;
            margin-top: 5px;
            font-size: 12px;
            border-left: 3px solid #3b82f6;
        }
        .legend {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            font-size: 12px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin: 4px 0;
        }
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 6px;
        }
    </style>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body>
    <div id="map-container">
        <div id="map"></div>
        <div class="map-overlay">
            <h3>CPAS Live GPS Map</h3>
            <div id="status">Loading...</div>
        </div>
        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background-color: #10b981;"></div>
                <span>On Patrol</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #f59e0b;"></div>
                <span>Responding</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background-color: #ef4444;"></div>
                <span>Needs Assistance</span>
            </div>
        </div>
    </div>

    <script>
        const API_ENDPOINT = '/admin/api/gps_data.php';
        const EMBED_TOKEN = '<?php echo $token; ?>';
        
        // Map initialization
        const map = L.map('map').setView([14.6970, 121.0880], 15);
        
        // Add tile layer (you can use your preferred tiles)
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);
        
        // Custom icons
        const icons = {
            'On Patrol': L.divIcon({
                html: `<div style="background:#10b981;width:20px;height:20px;border-radius:50%;border:3px solid white;box-shadow:0 0 8px rgba(0,0,0,0.3);"></div>`,
                className: 'patrol-icon',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            }),
            'Responding': L.divIcon({
                html: `<div style="background:#f59e0b;width:20px;height:20px;border-radius:50%;border:3px solid white;box-shadow:0 0 8px rgba(0,0,0,0.3);"></div>`,
                className: 'responding-icon',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            }),
            'Needs Assistance': L.divIcon({
                html: `<div style="background:#ef4444;width:20px;height:20px;border-radius:50%;border:3px solid white;box-shadow:0 0 8px rgba(0,0,0,0.3);"></div>`,
                className: 'assistance-icon',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            })
        };
        
        let markers = {};
        let updateInterval;
        
        // Fetch GPS data
        async function fetchGPSData() {
            try {
                const response = await fetch(`${API_ENDPOINT}?api_key=${EMBED_TOKEN}`, {
                    headers: {
                        'X-API-Key': EMBED_TOKEN
                    }
                });
                
                if (!response.ok) throw new Error('API request failed');
                
                const data = await response.json();
                
                if (data.success) {
                    updateMap(data.data.units);
                    document.getElementById('status').innerHTML = 
                        `<strong>${data.data.metadata.active_units} Active</strong> • Updated: ${new Date().toLocaleTimeString()}`;
                }
            } catch (error) {
                console.error('Error fetching GPS data:', error);
                document.getElementById('status').innerHTML = 'Connection error';
            }
        }
        
        // Update map markers
        function updateMap(units) {
            // Remove old markers
            Object.keys(markers).forEach(id => {
                if (!units.find(u => u.id == id)) {
                    map.removeLayer(markers[id]);
                    delete markers[id];
                }
            });
            
            // Add/update markers
            units.forEach(unit => {
                const lat = parseFloat(unit.latitude);
                const lng = parseFloat(unit.longitude);
                
                if (isNaN(lat) || isNaN(lng)) return;
                
                const icon = icons[unit.status] || icons['On Patrol'];
                
                if (markers[unit.id]) {
                    // Update existing marker position
                    markers[unit.id].setLatLng([lat, lng]);
                    markers[unit.id].setIcon(icon);
                } else {
                    // Create new marker
                    const marker = L.marker([lat, lng], { icon })
                        .addTo(map)
                        .bindPopup(`
                            <div class="unit-info">
                                <strong>${unit.callsign}</strong><br>
                                Status: ${unit.status}<br>
                                Speed: ${unit.speed || 0} km/h<br>
                                Battery: ${unit.battery_level || 'N/A'}%<br>
                                ${unit.assignment_area ? `Area: ${unit.assignment_area}<br>` : ''}
                                <small>Updated: ${new Date(unit.last_update).toLocaleTimeString()}</small>
                            </div>
                        `);
                    
                    markers[unit.id] = marker;
                }
            });
        }
        
        // Initialize
        fetchGPSData();
        updateInterval = setInterval(fetchGPSData, 10000); // Update every 10 seconds
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            clearInterval(updateInterval);
        });
    </script>
</body>
</html>