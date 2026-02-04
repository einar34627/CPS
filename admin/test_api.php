<?php
// test_api.php - Test the API from same server
echo "<h2>Testing GPS Data API</h2>";

// Test with different methods
$api_key = "TEST_KEY_123";
$base_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/gps_data.php";

echo "<h3>Test 1: Query Parameter</h3>";
$url1 = $base_url . "?api_key=" . $api_key;
echo "URL: <a href='$url1' target='_blank'>$url1</a><br>";

echo "<h3>Test 2: Direct JSON Output</h3>";
echo "<pre>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if ($data && isset($data['success']) && $data['success']) {
    echo "✓ API Working!\n";
    echo "Units found: " . count($data['units']) . "\n";
    echo "Active devices: " . $data['stats']['active_devices'] . "\n";
} else {
    echo "✗ API Error:\n";
    print_r($data);
}
echo "</pre>";

echo "<h3>Sample cURL Commands:</h3>";
echo "<code>curl '$url1'</code><br><br>";
echo "<code>curl -H 'X-API-Key: $api_key' '$base_url'</code>";
?>