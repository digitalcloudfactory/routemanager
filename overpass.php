<?php
// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST requests allowed']);
    exit;
}

// 1. Read raw JSON input from JS fetch payload
$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true);

// 2. Extract query (check JSON first, fallback to standard POST)
$query = $json['query'] ?? $_POST['query'] ?? null;

if (!$query) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing query parameter']);
    exit;
}

// 3. Send query to Overpass API
$overpassUrl = 'https://overpass-api.de/api/interpreter';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $overpassUrl,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'data=' . urlencode($query),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'WasmerRoutePlanner/1.0',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;