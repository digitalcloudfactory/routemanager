<?php
session_start();

ini_set('memory_limit', '512M'); // Raise it to 512MB or higher
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['internal_user_id'])) {
    header("Location: index.php");
    exit;
}
$internalUserId = $_SESSION['internal_user_id'];

/* ===============================
    DATABASE CONFIG
================================ */
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'dbcmpLT2zrmwmur5UEjZ3Xj8';
$db_user = 'de142c5d7a0180009884f0319fb7';
$db_pass = '0696de14-2c5d-7bb2-8000-fe77e5a731bf';

$pdo = new PDO(
    "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
    $db_user,
    $db_pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]
);

/* ===============================
   LOAD USER PROFILE
================================ */

$userStmt = $pdo->prepare("
    SELECT firstname, lastname, avatar,last_routes_sync
    FROM users
    WHERE id = ?
");
$userStmt->execute([$internalUserId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

/* ===============================
   LOAD ROUTES (PER USER)
================================ */

$stmt = $pdo->prepare("
    SELECT
        CAST(route_id AS CHAR) AS route_id,
        name,
        country,
        summary_polyline,
        DATE(created_at) AS created_date
    FROM strava_routes
    WHERE user_id = ?
    ORDER BY updated_at DESC
    LIMIT 455
");
$stmt->execute([$internalUserId]);


$routes = [];
$count = 0;

// Fetch row-by-row instead of fetchAll() to see exactly where it dies
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    // Write directly to Wasmer Edge logs
    file_put_contents('php://stderr', "Processing row #{$count} - Route ID: {$row['route_id']}\n");
    $routes[] = $row;
}

file_put_contents('php://stderr', "Successfully fetched all 455 rows!\n");



/* ===============================
    LOAD TAGS PER ROUTE
================================ */
file_put_contents('php://stderr', "Starting tag loading query...\n");

$tagStmt = $pdo->prepare("
    SELECT route_id, GROUP_CONCAT(tag ORDER BY tag SEPARATOR ', ') AS tags
    FROM route_tags
    WHERE route_id IN (
        SELECT route_id FROM strava_routes WHERE user_id = ?
    )
    GROUP BY route_id
");
$tagStmt->execute([$internalUserId]);
$tagsRaw = $tagStmt->fetchAll(PDO::FETCH_ASSOC);

file_put_contents('php://stderr', "Successfully loaded tags from DB. Mapping to array...\n");

$tagsByRoute = [];
foreach ($tagsRaw as $row) {
    $tagsByRoute[$row['route_id']] = $row['tags'];
}

/* ===============================
    ATTACH TAGS TO ROUTES
================================ */
file_put_contents('php://stderr', "Starting to attach tags to routes loop...\n");

$loopCount = 0;
foreach ($routes as &$route) {
    $loopCount++;
    // Log every 50 rows so we don't spam too hard, but can see progress
    if ($loopCount % 50 === 0 || $loopCount > 450) {
        file_put_contents('php://stderr', "Attaching tags to row #{$loopCount} (Route ID: {$route['route_id']})\n");
    }
    $route['tags'] = $tagsByRoute[$route['route_id']] ?? '';
}
unset($route);

file_put_contents('php://stderr', "Successfully attached tags to all routes!\n");


$countryStmt = $pdo->prepare("SELECT DISTINCT country FROM strava_routes WHERE user_id = ? AND country IS NOT NULL AND country != '' ORDER BY country ASC");
$countryStmt->execute([$internalUserId]);
$countries = $countryStmt->fetchAll(PDO::FETCH_COLUMN);

?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>

<style>
#map {
  display: block !important;
  visibility: visible !important;
  height: 600px !important;
  width: 100% !important;
  border-radius: 12px;
  position: relative !important;
  z-index: 10 !important;
  overflow: hidden !important; 
}
    
#filterPanel {
  position: fixed;
  top: 0;
  right: 0;
  width: 320px;
  height: 100%;
  background: var(--background-color);
  box-shadow: -4px 0 12px rgba(0,0,0,0.1);
  padding: 1rem;
  transform: translateX(100%);
  transition: transform 0.25s ease;
  z-index: 1000;
}

#filterPanel.open {
  transform: translateX(0);
}

main.container {
  max-width: 100%;    
  padding: 1rem 2rem; 
  box-sizing: border-box;
}

:root {
  --pico-font-size: 0.85rem;
  --pico-spacing: 0.5rem;
}

.leaflet-control-container {
  z-index: 500;
}
    
.range-slider input[type="range"]::-webkit-slider-thumb {
    pointer-events: auto;
    cursor: pointer;
}
.range-slider input[type="range"]::-moz-range-thumb {
    pointer-events: auto;
    cursor: pointer;
}
#filterDistanceMin {
    background: transparent !important;
}       
</style>

<main class="container">
<header class="grid">
  <div class="grid" style="align-items:center">
    <img src="<?= htmlspecialchars($user['avatar'] ?? '') ?>"
         alt="Avatar"
         width="64"
         style="border-radius:50%">
    <div>
      <strong><?= htmlspecialchars(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')) ?></strong><br>
      <small>Last Strava Sync: <?= !empty($user['last_routes_sync']) ? htmlspecialchars($user['last_routes_sync']) : '<em>Never synced</em>' ?></small>
    </div>
  </div>

  <section class="grid">
    <div>
      <a id="mapLink" href="routes.php" role="button" class="secondary">Table view</a>
    </div>    
    <div>
      <button id="fetchRoutes" type="button">Fetch new routes from Strava</button>
    </div>
    <div style="text-align:right">
      <button id="openFilters" class="secondary" type="button">Filters</button>
    </div>
  </section>    
</header>

  <div id="map"></div>

  <?php include 'filter_panel.php'; ?>
</main>

<script>
// Safe fallback data payload injection
const routes = <?= json_encode($routes ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?: '[]' ?>;

console.log("📦 Raw data dump from database:", routes);
    

</script>



<?php include 'footer.php'; ?>
