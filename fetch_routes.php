<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Session lock prevention: read what we need, then close.
if (!isset($_SESSION['internal_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
$internalUserId = $_SESSION['internal_user_id'];
session_write_close(); 

header('Content-Type: application/json');

// --- CONFIG ---
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'dbcmpLT2zrmwmur5UEjZ3Xj8';
$db_user = 'de142c5d7a0180009884f0319fb7';
$db_pass = '0696de14-2c5d-7bb2-8000-fe77e5a731bf';
$strava_client_id = '6839';
$strava_client_secret = '1a1057defe991fd6c2711f1199a3563cb3d5395f';

// Get current page from request, default to 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50; // Smaller batches are safer

// --- CONNECT DB ---
try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB Connection failed']);
    exit;
}

// ... at the very bottom of the script ...
$insert = null;
$existingStmt = null;
$pdo = null; // This closes the connection

// --- FETCH USER TOKENS ---
$stmt = $pdo->prepare("SELECT access_token, refresh_token, token_expires_at FROM users WHERE id = :id");
$stmt->execute([':id' => $internalUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$accessToken = $user['access_token'];

// (Token refresh logic remains the same as your original...)
if ($user['token_expires_at'] < time()) {
    $ch = curl_init("https://www.strava.com/oauth/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $strava_client_id,
        'client_secret' => $strava_client_secret,
        'grant_type' => 'refresh_token',
        'refresh_token' => $user['refresh_token']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (isset($res['access_token'])) {
        $accessToken = $res['access_token'];
        $pdo->prepare("UPDATE users SET access_token=?, refresh_token=?, token_expires_at=? WHERE id=?")
            ->execute([$accessToken, $res['refresh_token'], $res['expires_at'], $internalUserId]);
    }
}

// --- FETCH ONE PAGE FROM STRAVA ---
$ch = curl_init("https://www.strava.com/api/v3/athlete/routes?page=$page&per_page=$perPage");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['success' => false, 'error' => "Strava API Error: $httpCode"]);
    exit;
}

$routes = json_decode($response, true);
$hasMore = (is_array($routes) && count($routes) === $perPage);

// --- PROCESS BATCH ---
$insert = $pdo->prepare("
    INSERT INTO strava_routes (user_id, route_id, name, description, distance_km, elevation, type, private, starred, country, created_at, estimated_moving_time, summary_polyline)
    VALUES (:user, :rid, :name, :description, :distance, :elevation, :type, :private, :starred, :country, :created_at, :estimated_moving_time, :polyline)
    ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), distance_km=VALUES(distance_km), elevation=VALUES(elevation), 
    type=VALUES(type), private=VALUES(private), starred=VALUES(starred), country=IFNULL(country, VALUES(country)), 
    estimated_moving_time=VALUES(estimated_moving_time), summary_polyline=VALUES(summary_polyline)
");

$existingStmt = $pdo->prepare("SELECT country FROM strava_routes WHERE route_id = :rid AND user_id = :uid LIMIT 1");

$processed = 0;
$geocodesInThisBatch = 0;
$maxGeocodesPerBatch = 5; // Low limit to keep request fast

foreach ($routes as $route) {
    $rid = (string)$route['id'];
    $existingStmt->execute([':rid' => $rid, ':uid' => $internalUserId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    $country = $existing['country'] ?? null;

    // Geocode only if missing AND we haven't hit the small batch limit
    if (empty($country) && !empty($route['map']['summary_polyline']) && $geocodesInThisBatch < $maxGeocodesPerBatch) {
        $country = getCountryFromPolyline($route['map']['summary_polyline']);
        $geocodesInThisBatch++;
        if ($country) usleep(1200000); 
    }

    $insert->execute([
        ':user' => $internalUserId, ':rid' => $rid, ':name' => $route['name'],
        ':description' => $route['description'] ?? null, ':distance' => $route['distance'] / 1000,
        ':elevation' => $route['elevation_gain'], ':type' => $route['type'] ?? null,
        ':private' => $route['private'] ? 1 : 0, ':starred' => $route['starred'] ? 1 : 0,
        ':country' => $country, ':created_at' => !empty($route['created_at']) ? date('Y-m-d H:i:s', strtotime($route['created_at'])) : null,
        ':estimated_moving_time' => $route['estimated_moving_time'], ':polyline' => $route['map']['summary_polyline'] ?? null
    ]);
    $processed++;
}

// Update sync timestamp
$pdo->prepare("UPDATE users SET last_routes_sync = NOW() WHERE id = ?")->execute([$internalUserId]);

echo json_encode([
    'success' => true,
    'page_processed' => $page,
    'routes_in_batch' => $processed,
    'has_more' => $hasMore
]);

// --- HELPERS (Keep your existing functions below) ---
function getCountryFromPolyline($summaryPolyline) { /* same as before */ }
function decodePolyline($encoded) { /* same as before */ }
