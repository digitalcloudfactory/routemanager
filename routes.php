<?php
session_start();

// Database credentials
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'routes';
$db_user = '68a00bc6768780007ea0fea26ffa';
$db_pass = '069668a0-0bc6-788a-8000-597667343eee';

// Strava access token
if (!isset($_SESSION['access_token'])) {
    header("Location: index.php");
    exit;
}
$access_token = $_SESSION['access_token'];

// Connect to DB
try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch routes from Strava and store in DB if requested
if (isset($_POST['fetch_new'])) {
    $routes_url = "https://www.strava.com/api/v3/athlete/routes?per_page=5";
    $ch = curl_init($routes_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $routes_json = curl_exec($ch);
    curl_close($ch);

    $routes = json_decode($routes_json, true);

    if (!empty($routes)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO strava_routes (id, name, distance, elevation, type) VALUES (:id, :name, :distance, :elevation, :type)");

        foreach ($routes as $route) {
            $stm
