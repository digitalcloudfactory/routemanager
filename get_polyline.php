<?php

require_once 'config.php'; // 🟩 Everything loads instantly


// Access control layer: kick them out to index if they aren't authenticated
if (!isset($_SESSION['internal_user_id'])) {
    header("Location: index.php");
    exit;
}
$internalUserId = $_SESSION['internal_user_id'];

if (!isset($_SESSION['internal_user_id'])) exit;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$route_id = $_GET['route_id'] ?? '';

$stmt = $pdo->prepare("SELECT summary_polyline FROM strava_routes WHERE route_id = ?");
$stmt->execute([$route_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
// 2. Check if row exists and has a polyline
if ($row && !empty($row['summary_polyline'])) {
    echo json_encode(['polyline' => $row['summary_polyline']]);
} else {
    // Return a 404 or a successful empty state so JS knows there's no path
    echo json_encode(['polyline' => null, 'message' => 'No polyline found for this ID']);
}
exit;
