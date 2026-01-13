<?php
session_start();

if (!isset($_SESSION['access_token'])) {
    header("Location: index.php");
    exit;
}

$access_token = $_SESSION['access_token'];
$activities_url = "https://www.strava.com/api/v3/athlete/activities?per_page=5";

$ch = curl_init($activities_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$activities_json = curl_exec($ch);
curl_close($ch);

$activities = json_decode($activities_json, true);

echo "<h1>Latest 5 Strava Activities</h1>";
echo "<ul>";
foreach ($activities as $act) {
    echo "<li>{$act['name']} — {$act['distance']} meters — {$act['moving_time']} sec</li>";
}
echo "</ul>";
echo "<a href='logout.php'>Logout</a>";
?>
