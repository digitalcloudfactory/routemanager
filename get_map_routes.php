<?php
require_once 'config.php'; // 🟩 Everything loads instantly

// Access control layer: kick them out to index if they aren't authenticated
if (!isset($_SESSION['internal_user_id'])) {
    header("Location: index.php");
    exit;
}
$internalUserId = $_SESSION['internal_user_id'];

// Check if a specific country filter was requested via GET
$countryFilter = $_GET['country'] ?? 'all';

// 1. Fetch Routes (Filtered by country if provided, otherwise fetch all)
if ($countryFilter !== 'all' && !empty($countryFilter)) {
    $stmt = $pdo->prepare("SELECT CAST(route_id AS CHAR) AS route_id, 
                            name, 
                            country, 
                            distance_km,
                            elevation,
                            type,
                            estimated_moving_time,
                            private,
                            starred,
                            summary_polyline, 
                            DATE(created_at) AS created_date 
                            FROM strava_routes 
                            WHERE user_id = ? AND country = ?
                            ORDER BY updated_at DESC");
    $stmt->execute([$internalUserId, $countryFilter]);
} else {
    $stmt = $pdo->prepare("SELECT CAST(route_id AS CHAR) AS route_id, 
                            name, 
                            country, 
                            distance_km,
                            elevation,
                            type,
                            estimated_moving_time,
                            private,
                            starred,
                            summary_polyline, 
                            DATE(created_at) AS created_date 
                            FROM strava_routes 
                            WHERE user_id = ? 
                            ORDER BY updated_at DESC");
    $stmt->execute([$internalUserId]);
}

$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch & Attach Tags
if (!empty($routes)) {
    $tagStmt = $pdo->prepare("SELECT route_id, GROUP_CONCAT(tag ORDER BY tag SEPARATOR ', ') AS tags FROM route_tags WHERE route_id IN (SELECT route_id FROM strava_routes WHERE user_id = ?) GROUP BY route_id");
    $tagStmt->execute([$internalUserId]);
    $tagsRaw = $tagStmt->fetchAll(PDO::FETCH_ASSOC);

    $tagsByRoute = [];
    foreach ($tagsRaw as $row) {
        $tagsByRoute[$row['route_id']] = $row['tags'];
    }

    foreach ($routes as &$route) {
        $route['tags'] = $tagsByRoute[$route['route_id']] ?? '';
    }
}

// 3. Output as JSON
header('Content-Type: application/json');
echo json_encode($routes);
exit(0);
