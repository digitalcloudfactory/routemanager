<?php
session_start();


if (!isset($_SESSION['internal_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

// Use internal ID for all DB writes
$internalUserId = $_SESSION['internal_user_id'];

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$routeId = (string)($data['route_id'] ?? 0);
$tags = $data['tags'] ?? [];

error_log('Internal USER ID received: ' . $_SESSION['internal_user_id']);

$routeId = (string)($data['route_id'] ?? '');
$tags = $data['tags'] ?? [];

error_log('Route ID received: ' . $routeId);
error_log('Tags received: ' . json_encode($tags, JSON_UNESCAPED_UNICODE));

if ($routeId === '') {
    echo json_encode(['success' => false, 'error' => 'Invalid route']);
    exit;
}

// --- CONFIG ---
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'routes';
$db_user = '68a00bc6768780007ea0fea26ffa';
$db_pass = '069668a0-0bc6-788a-8000-597667343eee';
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
