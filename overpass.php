<?php
// api/overpass.php
header('Content-Type: application/json');

if (!isset($_GET['data'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing data parameter']);
    exit;
}

$query = $_GET['data'];
$url = 'https://overpass-api.de/api/interpreter?data=' . urlencode($query);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'MyMapApp/1.0'); // Overpass appreciates a user-agent header!

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;