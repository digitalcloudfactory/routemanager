<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set a higher time limit because fetching many routes + geocoding takes time
set_time_limit(300); 

if (!isset($_SESSION['internal_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$internalUserId = $_SESSION['internal_user_id'];
$stravaId = $_SESSION['strava_id'];

header('Content-Type: application/json');

// --- CONFIG ---
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'dbcmpLT2zrmwmur5UEjZ3Xj8';
$db_user = 'de142c5d7a0180009884f0319fb7';
$db_pass = '0696de14-2c5d-7bb2-8000-fe77e5a731bf';
$strava_client_id = '6839';
$strava_client_secret = '1a1057defe991fd6c2711f1199a3563cb3d5395f';

// --- CONNECT DB ---
try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB Connection failed']);
    exit;
}

// --- FETCH USER TOKENS ---
$stmt = $pdo->prepare("SELECT id, access_token, refresh_token, token_expires_at FROM users WHERE id = :id");
$stmt->execute([':id' => $internalUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$accessToken = $user['access_token'];
$refreshToken = $user['refresh_token'];
$expiresAt = $user['token_expires_at'];

// --- REFRESH TOKEN IF EXPIRED ---
if ($expiresAt < time()) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/oauth/token");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $strava_client_id,
        'client_secret' => $strava_client_secret,
        'grant_type' => 'refresh_token',
        'refresh_token' => $refreshToken
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'];
        $expiresAt = $data['expires_at'];
        $updateToken = $pdo->prepare("UPDATE users SET access_token = :access, refresh_token = :refresh, token_expires_at = :expires WHERE id = :id");
        $updateToken->execute([':access' => $accessToken, ':refresh' => $refreshToken, ':expires' => $expiresAt, ':id' => $internalUserId]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Token refresh failed']);
        exit;
    }
}

// --- FETCH ALL ROUTES USING PAGINATION ---
$page = 1;
$perPage = 100; // Strava max is 200, but 100 is safer for stability
$all_fetched_routes = [];
$keepFetching = true;

while ($keepFetching) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/api/v3/athlete/routes?page=$page&per_page=$perPage");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $pageRoutes = json_decode($response, true);

    if (is_array($pageRoutes) && count($pageRoutes) > 0) {
        $all_fetched_routes = array_merge($all_fetched_routes, $pageRoutes);
        $page++;
    } else {
        $keepFetching = false;
    }

    // Safety break to prevent infinite loops if API fails
    if ($page > 20) break; 
}

// --- PREPARE QUERIES ---
$insert = $pdo->prepare("
    INSERT INTO strava_routes 
    (user_id, route_id, name, description, distance_km, elevation, type, private, starred, country, created_at, estimated_moving_time, summary_polyline)
    VALUES (:user, :rid, :name, :description, :distance, :elevation, :type, :private, :starred, :country, :created_at, :estimated_moving_time, :polyline)
    ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    distance_km = VALUES(distance_km),
    elevation = VALUES(elevation),
    type = VALUES(type),
    private = VALUES(private),
    starred = VALUES(starred),
    country = IFNULL(country, VALUES(country)),
    estimated_moving_time = VALUES(estimated_moving_time),
    summary_polyline = VALUES(summary_polyline)
");

$existingStmt = $pdo->prepare("SELECT country FROM strava_routes WHERE route_id = :rid AND user_id = :uid LIMIT 1");

$count = 0;
foreach ($all_fetched_routes as $route) {
    $routeIdStr = (string)$route['id'];
    $summaryPolyline = $route['map']['summary_polyline'] ?? null;
    
    // Check for existing country
    $existingStmt->execute([':rid' => $routeIdStr, ':uid' => $internalUserId]);
    $existingRoute = $existingStmt->fetch(PDO::FETCH_ASSOC);
    
    $country = $existingRoute['country'] ?? null;

    // Only geocode if we don't have a country yet
    if (empty($country) && !empty($summaryPolyline)) {
        $country = getCountryFromPolyline($summaryPolyline);
        // Nominatim Rate Limit: 1 request per second
        usleep(1200000); 
    }

    $createdAt = !empty($route['created_at']) ? date('Y-m-d H:i:s', strtotime($route['created_at'])) : null;

    $insert->execute([
        ':user'                 => $internalUserId,
        ':rid'                  => $routeIdStr,
        ':name'                 => $route['name'],
        ':description'          => $route['description'] ?? null,
        ':distance'             => $route['distance'] / 1000,
        ':elevation'            => $route['elevation_gain'],
        ':type'                 => $route['type'] ?? null,
        ':private'              => $route['private'],
        ':starred'              => $route['starred'],
        ':country'              => $country,
        ':created_at'           => $createdAt,
        ':estimated_moving_time'=> $route['estimated_moving_time'],
        ':polyline'             => $summaryPolyline
    ]);
    $count++;
}

if ($count > 0) {
    $updateSync = $pdo->prepare("UPDATE users SET last_routes_sync = NOW() WHERE id = :id");
    $updateSync->execute([':id' => $internalUserId]);
}

echo json_encode([
    'success' => true,
    'routes_fetched' => $count,
    'last_sync' => date('Y-m-d H:i:s')
]);

// ... (Keep your getCountryFromPolyline and decodePolyline functions here) ...
