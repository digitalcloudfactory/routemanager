<?php
session_start();


if (!isset($_SESSION['internal_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Use internal ID for all DB writes
$internalUserId = $_SESSION['internal_user_id'];

// Optional: still keep strava_id if needed for API calls
$stravaId = $_SESSION['strava_id'];

header('Content-Type: application/json');
error_log('Session ID: ' . session_id());
error_log('Session contents: ' . print_r($_SESSION, true));

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
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'DB Connection failed']);
    exit;
}


// --- FETCH USER INTERNAL ID AND TOKENS ---
$stmt = $pdo->prepare("SELECT id, access_token, refresh_token, token_expires_at FROM users WHERE id = :id");
$stmt->execute([':id' => $internalUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$internalUserId = $user['id'];
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
        $updateToken->execute([
            ':access' => $accessToken,
            ':refresh' => $refreshToken,
            ':expires' => $expiresAt,
            ':id' => $internalUserId
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Token refresh failed']);
        exit;
    }
}

// --- FETCH ROUTES FROM STRAVA ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/api/v3/athlete/routes?per_page=50");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$routes = json_decode($response, true);
if (!is_array($routes)) {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch routes']);
    exit;
}


// --- PREPARE INSERT/UPDATE ---
$insert = $pdo->prepare("
    INSERT INTO strava_routes 
    (user_id, route_id, name, description, distance_km, elevation, type, private, starred, country ,created_at, estimated_moving_time, summary_polyline)
    VALUES (:user, :rid, :name, :description, :distance, :elevation, :type, :private, :starred, :country ,:created_at, :estimated_moving_time ,:polyline)
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


$count = 0;

$existingStmt = $pdo->prepare("
  SELECT country
  FROM strava_routes
  WHERE route_id = :rid AND user_id = :uid
  LIMIT 1
");


try {
    foreach ($routes as $route) {
        $routeType = $route['type'] ?? null;
        $createdAt = !empty($route['created_at']) ? date('Y-m-d H:i:s', strtotime($route['created_at'])) : null;
        $country = null;

        
        $summaryPolyline = $route['map']['summary_polyline'] ?? null;
        
        // Look up existing country
        $existingStmt->execute([
            ':rid' => (string)$route['id'],
            ':uid' => $internalUserId
        ]);
        
        $existingRoute = $existingStmt->fetch(PDO::FETCH_ASSOC);
        
        $country = null;
        
        if (
            !$existingRoute ||                          // new route
            empty($existingRoute['country'])            // or missing country
        ) {
            if (!empty($summaryPolyline)) {
                $country = getCountryFromPolyline($summaryPolyline);
        
                // Be polite to Nominatim
                usleep(1200000); // 1.2s
            }
        } else {
            // Reuse stored value
            $country = $existingRoute['country'];
        }

        
        
        $insert->execute([
            ':user'        => $internalUserId,
            ':rid'         => $route['id'],
            ':name'        => $route['name'],
            ':description' => $route['description'] ?? null,
            ':distance'    => $route['distance'] / 1000,
            ':elevation'   => $route['elevation_gain'],
            ':type'        => $routeType,
            ':private'   => $route['private'],
            ':starred'   => $route['starred'],
            ':country'  => $country,
            ':created_at'   => $createdAt,
            ':estimated_moving_time'    => $route['estimated_moving_time'],
            ':polyline'    => $route['map']['summary_polyline'] ?? null
        ]);
        $count++;
    }
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'error'=>'DB insert failed: '.$e->getMessage()]);
    exit;
}

// --- UPDATE last_routes_sync IF ROUTES INSERTED/UPDATED ---
if ($count > 0) {
    $updateSync = $pdo->prepare("UPDATE users SET last_routes_sync = NOW() WHERE id = :id");
    $updateSync->execute([':id' => $internalUserId]);
}

// --- RETURN JSON ---
echo json_encode([
    'success' => true,
    'routes_fetched' => $count,
    'last_sync' => date('Y-m-d H:i:s')
]);


function getCountryFromPolyline(string $summaryPolyline): ?string
{
    if (!$summaryPolyline) return null;

    // Decode polyline
    $coords = decodePolyline($summaryPolyline);
    if (!$coords || count($coords) === 0) return null;

    $lat = $coords[0][0];
    $lon = $coords[0][1];

    $url = "https://nominatim.openstreetmap.org/reverse"
         . "?lat=" . urlencode($lat)
         . "&lon=" . urlencode($lon)
         . "&format=json";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'User-Agent: MapRoutesApp/1.0 (contact@yourdomain.com)'
        ],
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    if (!$res) return null;

    $data = json_decode($res, true);
    return $data['address']['country'] ?? null;
}



function decodePolyline(string $encoded): array
{
    $points = [];
    $index = 0;
    $lat = 0;
    $lng = 0;

    $len = strlen($encoded);

    while ($index < $len) {
        $b = 0;
        $shift = 0;
        $result = 0;

        do {
            $b = ord($encoded[$index++]) - 63;
            $result |= ($b & 0x1f) << $shift;
            $shift += 5;
        } while ($b >= 0x20);

        $dlat = ($result & 1) ? ~($result >> 1) : ($result >> 1);
        $lat += $dlat;

        $shift = 0;
        $result = 0;

        do {
            $b = ord($encoded[$index++]) - 63;
            $result |= ($b & 0x1f) << $shift;
            $shift += 5;
        } while ($b >= 0x20);

        $dlng = ($result & 1) ? ~($result >> 1) : ($result >> 1);
        $lng += $dlng;

        $points[] = [$lat / 1e5, $lng / 1e5];
    }

    return $points;
}
