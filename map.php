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
$db_name = 'routes';
$db_user = '68a00bc6768780007ea0fea26ffa';
$db_pass = '069668a0-0bc6-788a-8000-597667343eee';

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
      <small>Last Strava Sync: <?= htmlspecialchars($user['last_routes_sync']) ?></small>
    </div>
  </div>

<section class="grid">
<div>
<a href="routes.php<?= htmlspecialchars($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '') ?>"
   role="button"
   class="secondary">
   Table view
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
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap'
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

<script src="routes_shared.js"></script>
<?php include 'footer.php'; ?>
