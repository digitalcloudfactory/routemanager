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

$countryStmt = $pdo->prepare("SELECT DISTINCT country FROM strava_routes WHERE user_id = ? AND country IS NOT NULL AND country != '' ORDER BY country ASC");
$countryStmt->execute([$internalUserId]);
$countries = $countryStmt->fetchAll(PDO::FETCH_COLUMN);

?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>

<script src="routes_shared.js"></script>


<style>
#map {
  display: block !important;
  visibility: visible !important;
  height: 600px !important;
  width: 100% !important;
  position: relative !important;
  z-index: 10 !important;
}

/* If you need to make sure the controls stack nicely, target the container wrapper instead */
.leaflet-control-container {
  z-index: 500 !important;
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

<section style="margin-top: 2rem; display: block; clear: both; width: 100%;">
    <div id="map"></div>
</section>

  <?php include 'filter_panel.php'; ?>
</main>


<script>
const chunkSize = 50;
let currentIndex = 0;
let currentRenderSet = []; 
let routes = []; // Starts empty, populated by fetch

// Initialize global map canvas instance immediately
const map = L.map('map', { trackResize: true }).setView([50.8503, 4.3517], 2);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap contributors'
}).addTo(map);

const routeBoundsGroup = L.featureGroup().addTo(map);

/**
 * --- THE BRIDGE FUNCTION ---
 * This is the exact function name routes_shared.js looks for and calls!
 */
function drawRoutes(targetArray) {
    // 1. Wipe any current paths off the map canvas
    routeBoundsGroup.clearLayers();
    
    // 2. Point our progressive drawing loops to the newly filtered dataset
    currentRenderSet = targetArray;
    currentIndex = 0;
    
    // 3. Start drawing the first chunk
    renderNextChunk();
}

function renderNextChunk() {
    const end = Math.min(currentIndex + chunkSize, currentRenderSet.length);
    
    for (let i = currentIndex; i < end; i++) {
        const route = currentRenderSet[i];
        
        if (route.summary_polyline) {
            try {
                const decodedPoints = polyline.decode(route.summary_polyline);
                L.polyline(decodedPoints, { 
                    color: '#ff4500', 
                    weight: 3, 
                    opacity: 0.6 
                }).addTo(routeBoundsGroup);
            } catch (e) {
                console.error("Failed to parse polyline for route ID:", route.route_id, e);
            }
        }
    }
    
    currentIndex = end;
    
    if (currentIndex < currentRenderSet.length) {
        setTimeout(renderNextChunk, 10);
    } else {
        // Auto-zoom map bounds around whatever matched the current filter criteria
        if (routeBoundsGroup.getLayers().length > 0) {
            map.fitBounds(routeBoundsGroup.getBounds(), { padding: [30, 30] });
        }
    }
}

// Fetch data dynamically via background API request
console.log("📥 Requesting routes payload from server...");
fetch('get_map_routes.php')
    .then(response => response.json())
    .then(data => {
        // 1. Store the loaded dataset into the global 'routes' array variable
        routes = data;
        console.log(`📦 Loaded ${routes.length} routes into memory.`);
        
        // 2. UNCOMMENT the hook inside your routes_shared.js or trigger an explicit initial apply pass
        if (typeof applyFilters === 'function') {
            applyFilters(); 
        } else {
            drawRoutes(routes); // Fallback if filters haven't completely mounted yet
        }
    })
    .catch(error => console.error('❌ Error fetching route data endpoint:', error));

</script>



<?php include 'footer.php'; ?>
<?php exit(0); // Forces a clean exit status code to the Wasmer runtime ?>
