<?php
// fetch_routes.php
session_start();
header('Content-Type: application/json');

// --- CONFIG ---
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'routes';
$db_user = '68a00bc6768780007ea0fea26ffa';
$db_pass = '069668a0-0bc6-788a-8000-597667343eee';
$strava_client_id = 'YOUR_CLIENT_ID';
$strava_client_secret = 'YOUR_CLIENT_SECRET';

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

// --- CHECK LOGIN ---
if (!isset($_SESSION['strava_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$stravaId = $_SESSION['strava_id'];

// --- FETCH USER INTERNAL ID AND TOKENS ---
$stmt = $pdo->prepare("SELECT id, access_token, refresh_token, token_expires_at FROM users WHERE strava_id = :strava_id");
$stmt->execute([':strava_id' => $stravaId]);
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
    (user_id, strava_route_id, name, description, distance_km, elevation, type, polyline)
    VALUES (:user, :rid, :name, :description, :distance, :elevation, :type, :polyline)
    ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    distance_km = VALUES(distance),
    elevation = VALUES(elevation),
    type = VALUES(type),
    polyline = VALUES(polyline)
");

$count = 0;

foreach ($routes as $route) {
    $insert->execute([
        ':user'        => $internalUserId,
        ':rid'         => $route['id'],
        ':name'        => $route['name'],
        ':description' => $route['description'] ?? null,
        ':distance'    => $route['distance'] / 1000,
        ':elevation'   => $route['elevation_gain'],
        ':type'        => $route['type'],
        ':polyline'    => $route['map']['summary_polyline'] ?? null
    ]);
    $count++;
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
