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
    header("Location: login.php");
    exit;
}
$access_token = $_SESSION['access_token'];

// Connect to DB
try {
$pdo = new PDO(
    "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
    $db_user,
    $db_pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]
);

    
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
            $stmt->execute([
                ':id' => $route['id'],
                ':name' => $route['name'],
                ':distance' => $route['distance'],
                ':elevation' => $route['elevation_gain'],
                ':type' => $route['type'] ?? 'Unknown'
            ]);
        }
        echo "<p>New routes fetched and stored!</p>";
    } else {
        echo "<p>No new routes found.</p>";
    }
}

// Read routes from DB
$stmt = $pdo->query("SELECT * FROM strava_routes ORDER BY id DESC LIMIT 5");
$routes_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Last 5 Stored Strava Routes</h1>
<form method="POST">
    <button type="submit" name="fetch_new">Fetch New Routes from Strava</button>
</form>

<?php
if (empty($routes_db)) {
    echo "<p>No routes in database.</p>";
} else {
    echo "<ul>";
    foreach ($routes_db as $route) {
        $name = htmlspecialchars($route['name']);
        $distance_km = round($route['distance'] / 1000, 2);
        $elevation = $route['elevation'];
        $type = $route['type'];
        echo "<li><strong>$name</strong> — $distance_km km — Elevation Gain: $elevation m — Type: $type</li>";
    }
    echo "</ul>";
}

echo "<a href='logout.php'>Logout</a>";
?>
