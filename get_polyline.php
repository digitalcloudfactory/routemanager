<?php
session_start();
if (!isset($_SESSION['internal_user_id'])) exit;

// ... [Your PDO DB Connection code here] ...

$route_id = $_GET['route_id'] ?? '';

$stmt = $pdo->prepare("SELECT summary_polyline FROM strava_routes WHERE route_id = ?");
$stmt->execute([$route_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['polyline' => $row['summary_polyline'] ?? '']);
