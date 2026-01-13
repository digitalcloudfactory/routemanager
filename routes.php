<?php
session_start();

if (!isset($_SESSION['access_token'])) {
    header("Location: index.php");
    exit;
}

$access_token = $_SESSION['access_token'];
$routes_url = "https://www.strava.com/api/v3/athlete/routes?per_page=5";

$ch = curl_init($routes_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$routes_json = curl_exec($ch);
curl_close($ch);

$routes = json_decode($routes_json, true);

echo "<h1>Last 5 Strava Routes</h1>";

if (empty($routes)) {
    echo "<p>No routes found.</p>";
} else {
    echo "<ul>";
    foreach ($routes as $route) {
        $name = htmlspecialchars($route['name']);
        $distance_km = round($route['distance'] / 1000, 2); // meters → km
        $elevation = $route['elevation_gain']; // in meters
        $type = $route['type'] ?? 'Unknown';
        echo "<li><strong>$name</strong> — $distance_km km — Elevation Gain: $elevation m — Type: $type</li>";
    }
    echo "</ul>";
}

echo "<a href='logout.php'>Logout</a>";
?>
