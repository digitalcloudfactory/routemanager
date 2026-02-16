<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

set_time_limit(600); // Increased to 10 mins for large accounts

if (!isset($_SESSION['internal_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$internalUserId = $_SESSION['internal_user_id'];
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

// --- FETCH ALL ROUTES ---
$page = 1;
$perPage = 100; 
$all_fetched_routes = [];
$keepFetching = true;

error_log("Starting Strava sync for User $internalUserId");

while ($keepFetching) {
    error_log("Fetching page $page from Strava...");
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/api/v3/athlete/routes?page=$page&per_page=$perPage");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $pageRoutes = json_decode($response, true);

    if (is_array($pageRoutes) && count($pageRoutes) > 0) {
        $all_fetched_routes = array_merge($all_fetched_routes, $pageRoutes);
        error_log("Found " . count($pageRoutes) . " routes on page $page.");
        $page++;
    } else {
        $keepFetching = false;
    }
    
    if ($page > 50) break; 
}

$totalRoutes = count($all_fetched_routes);
error_log("Total routes to process: $totalRoutes");

// --- PROCESS ROUTES ---
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
foreach ($all_fetched_routes as $index => $route) {
    $currentNum = $index + 1;
    $routeIdStr = (string)$route['id'];
    
    // Check for existing country
    $existingStmt->execute([':rid' => $routeIdStr, ':uid' => $internalUserId]);
    $existingRoute = $existingStmt->fetch(PDO::FETCH_ASSOC);
    $country = $existingRoute['country'] ?? null;

    if (empty($country) && !empty($route['map']['summary_polyline'])) {
        error_log("[$currentNum/$totalRoutes] Geocoding new route: " . $route['name']);
        $country = getCountryFromPolyline($route['map']['summary_polyline']);
        usleep(1200000); 
    } else {
        error_log("[$currentNum/$totalRoutes] Skipping geocode (already exists): " . $route['name']);
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
        ':private'              => $route['private'] ? 1 : 0,
        ':starred'              => $route['starred'] ? 1 : 0,
        ':country'              => $country,
        ':created_at'           => $createdAt,
        ':estimated_moving_time'=> $route['estimated_moving_time'],
        ':polyline'             => $route['map']['summary_polyline'] ?? null
    ]);
    $count++;
}

if ($count > 0) {
    $updateSync = $pdo->prepare("UPDATE users SET last_routes_sync = NOW() WHERE id = :id");
    $updateSync->execute([':id' => $internalUserId]);
}

error_log("Sync complete for User $internalUserId. $count routes processed.");

echo json_encode([
    'success' => true,
    'routes_fetched' => $count,
    'last_sync' => date('Y-m-d H:i:s')
]);

// --- HELPER FUNCTIONS ---
function getCountryFromPolyline(string $summaryPolyline): ?string {
    if (!$summaryPolyline) return null;
    $coords = decodePolyline($summaryPolyline);
    if (!$coords || count($coords) === 0) return null;
    $lat = $coords[0][0];
    $lon = $coords[0][1];
    
    // Added &accept-language=nl to the URL
    $url = "https://nominatim.openstreetmap.org/reverse"
         . "?lat=" . urlencode($lat)
         . "&lon=" . urlencode($lon)
         . "&format=json"
         . "&accept-language=nl"; // Forces Dutch naming
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['User-Agent: MapRoutesApp/1.0 (contact@yourdomain.com)'],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return null;
    $data = json_decode($res, true);
    return $data['address']['country'] ?? null;
}

function decodePolyline(string $encoded): array {
    $points = []; $index = 0; $lat = 0; $lng = 0; $len = strlen($encoded);
    while ($index < $len) {
        $b = 0; $shift = 0; $result = 0;
        do { $b = ord($encoded[$index++]) - 63; $result |= ($b & 0x1f) << $shift; $shift += 5; } while ($b >= 0x20);
        $dlat = ($result & 1) ? ~($result >> 1) : ($result >> 1); $lat += $dlat;
        $shift = 0; $result = 0;
        do { $b = ord($encoded[$index++]) - 63; $result |= ($b & 0x1f) << $shift; $shift += 5; } while ($b >= 0x20);
        $dlng = ($result & 1) ? ~($result >> 1) : ($result >> 1); $lng += $dlng;
        $points[] = [$lat / 1e5, $lng / 1e5];
    }
    return $points;
}
