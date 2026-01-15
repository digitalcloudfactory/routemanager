<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['internal_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$routeId = (int)($data['route_id'] ?? 0);
$tags = $data['tags'] ?? [];

if (!$routeId) {
    echo json_encode(['success' => false, 'error' => 'Invalid route']);
    exit;
}

/* DB */
$pdo = new PDO(
    "mysql:host=db.fr-pari1.bengt.wasmernet.com;port=10272;dbname=routes;charset=utf8mb4",
    "68a00bc6768780007ea0fea26ffa",
    "069668a0-0bc6-788a-8000-597667343eee",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

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
