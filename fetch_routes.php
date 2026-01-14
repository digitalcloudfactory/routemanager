<?php
session_start();
header('Content-Type: application/json');

/* ===============================
   AUTH CHECK
================================ */

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

/* ===============================
   CONFIG
================================ */

$client_id     = '6839';
$client_secret = '1a1057defe991fd6c2711f1199a3563cb3d5395f';

// Database credentials
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'routes';
$db_user = '68a00bc6768780007ea0fea26ffa';
$db_pass = '069668a0-0bc6-788a-8000-597667343eee';

/* ===============================
   DATABASE CONNECTION
================================ */

try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

/* ===============================
   LOAD USER TOKENS
================================ */

$stmt = $pdo->prepare("SELECT * FROM users WHERE strava_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$access_token  = $user['access_token'];
$refresh_token = $user['refresh_token'];
$expires_at    = $user['expires_at'];

$userStmt = $pdo->prepare("SELECT id, last_routes_sync FROM users WHERE id = :id");
$userStmt->execute([':id' => $user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);


/* ===============================
   TOKEN REFRESH (IF EXPIRED)
================================ */

if ($expires_at <= time()) {

    $ch = curl_init("https://www.strava.com/oauth/token");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token
        ])
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!isset($data['access_token'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Token refresh failed']);
        exit;
    }

    $access_token  = $data['access_token'];
    $refresh_token = $data['refresh_token'];
    $expires_at    = $data['expires_at'];

    $stmt = $pdo->prepare("
        UPDATE users
        SET access_token = ?, refresh_token = ?, expires_at = ?
        WHERE strava_id = ?
    ");
    $stmt->execute([$access_token, $refresh_token, $expires_at, $user_id]);
}

/* ===============================
   FETCH ROUTES FROM STRAVA
================================ */

$ch = curl_init("https://www.strava.com/api/v3/athlete/routes?per_page=10");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $access_token"
    ]
]);

$response = curl_exec($ch);
curl_close($ch);

$routes = json_decode($response, true);



if (!is_array($routes)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch routes']);
    exit;
}

/* ===============================
   STORE ROUTES (PER USER)
================================ */


$insert = $pdo->prepare("
    INSERT INTO strava_routes
      (
        user_id,
        route_id,
        name,
        description,
        distance_km,
        elevation,
        type,
        summary_polyline,
        updated_at
      )
    VALUES
      (
        :user,
        :rid,
        :name,
        :description,
        :distance,
        :elevation,
        :type,
        :polyline,
        NOW()
      )
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        distance_km = VALUES(distance_km),
        elevation = VALUES(elevation),
        type = VALUES(type),
        summary_polyline = VALUES(summary_polyline),
        updated_at = NOW()
");

$count = 0;

foreach ($routes as $route) {

    $insert->execute([
        ':user'        => $user_id,
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

if ($count > 0) {
    $updateSync = $pdo->prepare("
        UPDATE users
        SET last_routes_sync = NOW()
        WHERE id = :id
    ");
    $updateSync->execute([':id' => $user_id]);
}

error_log("Inserted/updated routes: $count");

/* ===============================
   RESPONSE
================================ */

echo json_encode([
    'success' => true,
    'routes_fetched' => $count
]);
