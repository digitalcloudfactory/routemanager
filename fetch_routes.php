<?php
session_start();
header('Content-Type: application/json');

// Database & Strava token setup
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'routes';
$db_user = '68a00bc6768780007ea0fea26ffa';
$db_pass = '069668a0-0bc6-788a-8000-597667343eee';
$access_token = $_SESSION['access_token'] ?? null;

if (!$access_token) {
    echo json_encode(['success'=>false]);
    exit;
}

// DB connection
$pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",$db_user,$db_pass,[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Fetch routes from Strava
$ch = curl_init("https://www.strava.com/api/v3/athlete/routes?per_page=10");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$routes = json_decode($response,true);
if(!$routes) $routes=[];

// Insert into DB
$stmt = $pdo->prepare("INSERT IGNORE INTO strava_routes (id,name,distance,elevation,type) VALUES (:id,:name,:distance,:elevation,:type)");
foreach($routes as $r){
    $stmt->execute([
        ':id'=>$r['id'],
        ':name'=>$r['name'],
        ':distance'=>$r['distance'],
        ':elevation'=>$r['elevation_gain'],
        ':type'=>$r['type'] ?? 'Unknown'
    ]);
}

// Return latest routes
$stmt = $pdo->query("SELECT * FROM strava_routes ORDER BY id DESC LIMIT 50");
$latest = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success'=>true,'routes'=>$latest]);
