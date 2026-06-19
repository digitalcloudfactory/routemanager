<?php
session_start();
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
        description,
        distance_km,
        elevation,
        type,
        estimated_moving_time,
        summary_polyline,
        DATE(created_at) AS created_date
    FROM strava_routes
    WHERE user_id = ?
    ORDER BY updated_at DESC
");
$stmt->execute([$internalUserId]);
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ===============================
   LOAD TAGS PER ROUTE
================================ */

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

$tagsByRoute = [];
foreach ($tagsRaw as $row) {
    $tagsByRoute[$row['route_id']] = $row['tags'];
}

/* ===============================
   attach tags to routes
================================ */
foreach ($routes as &$route) {
    $route['tags'] = $tagsByRoute[$route['route_id']] ?? '';
}
unset($route);



?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>

<style>
    
#map {
  display: block !important;
  height: 600px !important; /* Temporarily use a fixed size to guarantee visibility */
  width: 100% !important;
  max-width: 100%;
  border-radius: 12px;
  background: #e5e5e5; /* Gives a gray background so you can see if the container is rendering */
  position: relative !important;
    z-index: 10 !important; /* Pulls the map visual layer forward */
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
  max-width: 100%;    /* override container width */
  padding: 1rem 2rem; /* some horizontal padding */
  box-sizing: border-box;
}

:root {
  --pico-font-size: 0.85rem;
  --pico-spacing: 0.5rem;
}

    /* Fix for Leaflet controls showing over the panel */
.leaflet-control-container {
  z-index: 500;
}
    
/* Code for the filterpanel distance range slider -- Allow the thumbs to be interactive even when overlapping */
.range-slider input[type="range"]::-webkit-slider-thumb {
    pointer-events: auto;
    cursor: pointer;
}
.range-slider input[type="range"]::-moz-range-thumb {
    pointer-events: auto;
    cursor: pointer;
}
/* Ensure the track doesn't block the slider below */
#filterDistanceMin {
    background: transparent !important;
}    
    
</style>

<body>
<main class="container">

<header class="grid">
  <div class="grid" style="align-items:center">
    <img src="<?= htmlspecialchars($user['avatar']) ?>"
         alt="Avatar"
         width="64"
         style="border-radius:50%">
    <div>
      <strong><?= htmlspecialchars($user['firstname'].' '.$user['lastname']) ?></strong><br>
      <small>Last Strava Sync:<?= $user['last_routes_sync']? htmlspecialchars($user['last_routes_sync']): '<em>Never synced</em>' ?></small>
    </div>
  </div>

<section class="grid">
<div>
<a id="mapLink" href="routes.php" role="button" class="secondary">Table view</a>
</div>    
    <div>
    <button id="fetchRoutes" type="button">
      Fetch new routes from Strava
    </button>
  </div>
  <div style="text-align:right">
    <button id="openFilters" class="secondary" type="button">
      Filters
    </button>
  </div>
</section>    
</header>

  <div id="map"></div>

  <?php include 'filter_panel.php'; ?>
</main>




<script>
// 1. Expose the raw PHP database payload
const routes = <?= json_encode($routes, JSON_UNESCAPED_UNICODE); ?>;

// 2. Initialize the map instantly on the DOM element
const map = L.map('map', {
    trackResize: true
}).setView([48.8566, 2.3522], 4);

// 3. Attach standard OpenStreetMap tiles
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// 4. Force Leaflet to wake up and see its actual container size immediately
map.invalidateSize();

let routeLayers = [];

function clearRoutes() {
    routeLayers.forEach(l => map.removeLayer(l));
    routeLayers = [];
}

function drawRoutes(data) {
    clearRoutes();

    if (!data || data.length === 0) {
        return; 
    }

    data.forEach(route => {
        if (!route.summary_polyline) return;

        try {
            const coords = polyline.decode(route.summary_polyline).map(c => [c[0], c[1]]);

            const line = L.polyline(coords, {
                weight: 4,
                opacity: 0.8,
                color: '#ff5722'
            }).addTo(map);

            line.bindPopup(`
                <strong>${route.name}</strong><br>
                ${Number(route.distance_km).toFixed(1)} km<br>
                ${route.tags || ''}
            `);

            routeLayers.push(line);
        } catch (e) {
            console.error("Failed to decode polyline:", e);
        }
    });

    if (routeLayers.length > 0) {
        const group = L.featureGroup(routeLayers);
        map.fitBounds(group.getBounds(), { padding: [30, 30] });
    }
}

// 5. Run the initial draw execution
drawRoutes(routes);

// 6. Final safety net: trigger a layout refresh 200ms later to handle any slow CSS paints
setTimeout(() => {
    console.log("Forcing map redraw...");
    map.invalidateSize(true); // 'true' forces a total redraw animation recalculation
}, 200);
</script>

<script src="routes_shared.js?v=1.0.1"></script>

  <script>
  const mapLink = document.getElementById('mapLink');

  function updateMapLinkFromURL() {
    if (!mapLink) return;
    mapLink.href = 'routes.php' + window.location.search;
  }

  // Update initially and whenever filters change URL
  updateMapLinkFromURL();

  window.addEventListener('popstate', updateMapLinkFromURL);

  // Hook into URL updates from filters
  const originalReplace = history.replaceState;
  history.replaceState = function (...args) {
    originalReplace.apply(this, args);
    updateMapLinkFromURL();
  };
</script>
    
<?php include 'footer.php'; ?>
