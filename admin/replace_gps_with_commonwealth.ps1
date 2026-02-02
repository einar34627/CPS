
$path = "c:\xampp\htdocs\CPS\admin\admin_dashboard.php"
$lines = Get-Content $path
$part1 = $lines[0..5352]

$leafletLoader = @"
        async function initCommonwealthMap(){
            const el = document.getElementById('gps-leaflet-map');
            if (!el) return;
            
            if (!window.L || !L.map) {
                await new Promise((resolve, reject) => {
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    document.head.appendChild(link);
                    const s = document.createElement('script');
                    s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                    s.async = true;
                    s.defer = true;
                    s.onload = resolve;
                    s.onerror = () => reject(new Error('leaflet failed'));
                    document.head.appendChild(s);
                });
            }
"@

# Extract the Commonwealth map logic (Line 5454 to 5556)
# PowerShell array index = Line Number - 1
$mapLogic = $lines[5453..5555]

$closer = @"
        }
        initCommonwealthMap();
"@

# Start of Admin Chat logic (Line 5865)
$part3 = $lines[5864..($lines.Count-1)]

$newContent = $part1 + $leafletLoader + $mapLogic + $closer + $part3
$newContent | Set-Content $path -Encoding UTF8
Write-Host "GPS Map Replaced with Commonwealth Map successfully."
