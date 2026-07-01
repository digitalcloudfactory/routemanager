<?php
session_set_cookie_params([
    'lifetime' => 1209600,
    'path' => '/',
    'domain' => '', // Automatically uses current domain
    'secure' => false, // Set to true if your site uses https://
    'httponly' => true, // Security best practice: protects cookie from JS injection
    'samesite' => 'Lax'
]);

session_start();

ini_set('display_errors', 0); // Turned off on-screen errors so they don't corrupt JSON strings
error_reporting(E_ALL);

// Session lock prevention: read what we need, then close.
if (!isset($_SESSION['internal_user_id'])) {
    header('Content-Type: application/json');
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
$perPage = 50; 

// --- CONNECT DB ---
try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB Connection failed']);
    exit;
}

// --- FETCH USER TOKENS ---
$stmt = $pdo->prepare("SELECT access_token, refresh_token, token_expires_at FROM users WHERE id = :id");
$stmt->execute([':id' => $internalUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$accessToken = $user['access_token'];
$syncTime = date('Y-m-d H:i:s');

// Token refresh logic
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

// Fetch all existing route IDs for this user to avoid duplicating logic overloads
$existingStmt = $pdo->prepare("SELECT route_id FROM strava_routes WHERE user_id = ?");
$existingStmt->execute([$internalUserId]);
$existingIds = $existingStmt->fetchAll(PDO::FETCH_COLUMN);
$existingIdsMap = array_flip($existingIds); 

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
    INSERT INTO strava_routes (
        user_id, route_id, name, description, distance_km, elevation, type, private, starred, country, 
        created_at, estimated_moving_time, summary_polyline, strava_last_seen_at,
        start_latlng_lat, start_latlng_lng, end_latlng_lat, end_latlng_lng
    )
    VALUES (
        :user, :rid, :name, :description, :distance, :elevation, :type, :private, :starred, :country, 
        :created_at, :estimated_moving_time, :polyline, :last_seen,
        :s_lat, :s_lng, :e_lat, :e_lng
    )
    ON DUPLICATE KEY UPDATE 
        name=VALUES(name), 
        description=VALUES(description), 
        distance_km=VALUES(distance_km), 
        elevation=VALUES(elevation), 
        type=VALUES(type), 
        private=VALUES(private), 
        starred=VALUES(starred), 
        country=IFNULL(country, VALUES(country)), 
        estimated_moving_time=VALUES(estimated_moving_time), 
        summary_polyline=VALUES(summary_polyline),
        strava_last_seen_at=VALUES(strava_last_seen_at),
        start_latlng_lat=VALUES(start_latlng_lat),
        start_latlng_lng=VALUES(start_latlng_lng),
        end_latlng_lat=VALUES(end_latlng_lat),
        end_latlng_lng=VALUES(end_latlng_lng)
");

$processed = 0;
$geocodesInThisBatch = 0;
$maxGeocodesPerBatch = 5; 

foreach ($routes as $route) {
    $rid = (string)$route['id_str'];
    $isNewRoute = !isset($existingIdsMap[$rid]);
    $country = null;
    
    $start_lat = null; $start_lng = null;
    $end_lat = null; $end_lng = null;

    if (!empty($route['map']['summary_polyline'])) {
        $decoded = decodePolyline($route['map']['summary_polyline']);
        if (count($decoded) >= 2) {
            $start_lat = $decoded[0][0];
            $start_lng = $decoded[0][1];
            $end_lat   = end($decoded)[0];
            $end_lng   = end($decoded)[1];
            
            // Geocode only if it's a completely new route
            if ($isNewRoute && ($geocodesInThisBatch < $maxGeocodesPerBatch)) {
                $country = getCountryFromPolyline($route['map']['summary_polyline']);
                $geocodesInThisBatch++;
                if ($country) usleep(1200000); // 1.2s sleep for Nominatim policy requirements
            }
        }
    }   

    $insert->execute([
        ':user' => $internalUserId, ':rid' => $rid, ':name' => $route['name'],
        ':description' => $route['description'] ?? null, ':distance' => $route['distance'] / 1000,
        ':elevation' => $route['elevation_gain'], ':type' => $route['type'] ?? null,
        ':private' => $route['private'] ? 1 : 0, ':starred' => $route['starred'] ? 1 : 0,
        ':country' => $country, 
        ':created_at' => !empty($route['created_at']) ? date('Y-m-d H:i:s', strtotime($route['created_at'])) : null,
        ':estimated_moving_time' => $route['estimated_moving_time'], 
        ':polyline' => $route['map']['summary_polyline'] ?? null,
        ':last_seen' => $syncTime,
        ':s_lat' => $start_lat, ':s_lng' => $start_lng,
        ':e_lat' => $end_lat, ':e_lng' => $end_lng
    ]);
    $processed++;
}

// Update overall sync timestamp for profile dashboard indicator
$pdo->prepare("UPDATE users SET last_routes_sync = NOW() WHERE id = ?")->execute([$internalUserId]);

// Run deletion maintenance only on the final page pass
if (!$hasMore) {
    $cutoffTime = date('Y-m-d H:i:s', strtotime('-20 minutes'));
    $deleteStmt = $pdo->prepare("
        DELETE FROM strava_routes 
        WHERE user_id = ? 
        AND (strava_last_seen_at < ? OR strava_last_seen_at IS NULL)
    ");
    $deleteStmt->execute([$internalUserId, $cutoffTime]);
}

// Clean connection references explicitly
$insert = null;
$existingStmt = null;
$pdo = null;

// Return JSON payload safely at the very end
echo json_encode([
    'success' => true,
    'page_processed' => $page,
    'routes_in_batch' => $processed,
    'has_more' => $hasMore
]);
exit(0);

// --- HELPERS ---
function getCountryFromPolyline(string $summaryPolyline): ?string {
    if (!$summaryPolyline) return null;
    $coords = decodePolyline($summaryPolyline);
    if (!$coords || count($coords) === 0) return null;
    $lat = $coords[0][0];
    $lon = $coords[0][1];
    
    $url = "https://nominatim.openstreetmap.org/reverse"
         . "?lat=" . urlencode($lat)
         . "&lon=" . urlencode($lon)
         . "&format=json"
         . "&accept-language=nl"; 
    
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
