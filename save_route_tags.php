<?php

require_once 'config.php'; // 🟩 Everything loads instantly


// Access control layer: kick them out to index if they aren't authenticated
if (!isset($_SESSION['internal_user_id'])) {
    header("Location: index.php");
    exit;
}

// Use internal ID for all DB writes
$internalUserId = $_SESSION['internal_user_id'];

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

//$routeId = (string)($data['route_id'] ?? '');
$routeId = (int)($data['route_id'] ?? 0);
$tags = $data['tags'] ?? [];

error_log('Route ID received: ' . $routeId);
error_log('User ID received: ' . $internalUserId);
error_log('Tags received: ' . json_encode($tags, JSON_UNESCAPED_UNICODE));

if ($routeId === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid route']);
    exit;
}

/* Security: ensure route belongs to user */
$check = $pdo->prepare("
    SELECT 1 FROM strava_routes
    WHERE route_id = ? AND user_id = ?
");
$check->execute([$routeId, $_SESSION['internal_user_id']]);

if (!$check->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo->beginTransaction();

/* Remove old tags */
$pdo->prepare("DELETE FROM route_tags WHERE route_id = ?")
    ->execute([$routeId]);

/* Insert new tags */
$insert = $pdo->prepare("
    INSERT INTO route_tags (route_id, tag)
    VALUES (?, ?)
");

foreach ($tags as $tag) {
    $insert->execute([$routeId, mb_substr($tag, 0, 50)]);
}

$pdo->commit();

echo json_encode(['success' => true]);
