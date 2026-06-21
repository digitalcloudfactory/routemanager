<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['internal_user_id'])) {
    echo json_encode([]);
    exit;
}

$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'dbcmpLT2zrmwmur5UEjZ3Xj8';
$db_user = 'de142c5d7a0180009884f0319fb7';
$db_pass = '0696de14-2c5d-7bb2-8000-fe77e5a731bf';

$pdo = new PDO(
    "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
    $db_user,
    $db_pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// 1. Fetch Routes
$stmt = $pdo->prepare("SELECT CAST(route_id AS CHAR) AS route_id, name, country, summary_polyline, DATE(created_at) AS created_date FROM strava_routes WHERE user_id = ? ORDER BY updated_at DESC");
$stmt->execute([$_SESSION['internal_user_id']]);
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch & Attach Tags
$tagStmt = $pdo->prepare("SELECT route_id, GROUP_CONCAT(tag ORDER BY tag SEPARATOR ', ') AS tags FROM route_tags WHERE route_id IN (SELECT route_id FROM strava_routes WHERE user_id = ?) GROUP BY route_id");
$tagStmt->execute([$_SESSION['internal_user_id']]);
$tagsRaw = $tagStmt->fetchAll(PDO::FETCH_ASSOC);

$tagsByRoute = [];
foreach ($tagsRaw as $row) {
    $tagsByRoute[$row['route_id']] = $row['tags'];
}

foreach ($routes as &$route) {
    $route['tags'] = $tagsByRoute[$route['route_id']] ?? '';
}

echo json_encode($routes);
exit(0);
