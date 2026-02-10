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
  height: calc(100vh - 120px);
  border-radius: 12px;
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

    /* Fix for Leaflet controls showing over the panel */
.leaflet-control-container {
  z-index: 500;
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
</a>
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
const routes = <?= json_encode($routes, JSON_UNESCAPED_UNICODE); ?>;

const map = L.map('map');
L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
  attribution: '© OpenStreetMap contributors © CARTO',
  subdomains: 'abcd',
  maxZoom: 20
}).addTo(map);


let routeLayers = [];

function clearRoutes() {
  routeLayers.forEach(l => map.removeLayer(l));
  routeLayers = [];
}

function drawRoutes(data) {
  clearRoutes();

  data.forEach(route => {
    if (!route.summary_polyline) return;

    const coords = polyline.decode(route.summary_polyline)
      .map(c => [c[0], c[1]]);

    const line = L.polyline(coords, {
      weight: 3,
      opacity: 0.8
    }).addTo(map);

    line.bindPopup(`
      <strong>${route.name}</strong><br>
      ${Number(route.distance_km).toFixed(1)} km<br>
      ${route.tags || ''}
    `);

    routeLayers.push(line);
  });

  if (routeLayers.length) {
    const group = L.featureGroup(routeLayers);
    map.fitBounds(group.getBounds(), { padding: [20, 20] });
  }
}

drawRoutes(routes);
    
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
