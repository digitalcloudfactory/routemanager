<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* DB CONFIG */
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

/* LOAD USER PROFILE */
$userStmt = $pdo->prepare("SELECT firstname, lastname, avatar FROM users WHERE strava_id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

/* LOAD ROUTES */
$stmt = $pdo->prepare("
    SELECT *
    FROM strava_routes
    WHERE user_id = ?
    ORDER BY updated_at DESC
");
$stmt->execute([$user_id]);
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<body>
<main class="container">

<!-- PROFILE HEADER -->
<header class="grid">
  <div class="grid" style="align-items:center">
    <img src="<?= htmlspecialchars($user['avatar']) ?>" width="64" style="border-radius:50%">
    <div>
      <strong><?= htmlspecialchars($user['firstname'].' '.$user['lastname']) ?></strong><br>
      <small>Strava athlete</small>
    </div>
  </div>
  <div style="text-align:right">
  <button id="themeToggle" class="secondary">🌙</button>
    <a href="logout.php" role="button" class="secondary">Logout</a>
  </div>
</header>

<!-- TABLE -->
<section>
<figure style="overflow-x:auto">
<table class="striped hover">
<thead>
<tr>
  <th>Name</th>
  <th>Distance (km)</th>
  <th>Elevation (m)</th>
  <th>Type</th>
</tr>
</thead>
<tbody id="routesBody"></tbody>
</table>
</figure>
</section>

<!-- ROUTE MODAL -->
<dialog id="routeModal">
<article>
<header>
  <strong id="modalName"></strong>
  <a href="#" aria-label="Close" class="close" onclick="routeModal.close()"></a>
</header>

<p id="modalMeta"></p>
<p id="modalDesc"></p>

<div id="map"></div>

<footer>
  <a id="stravaLink" href="#" target="_blank" role="button">Open on Strava</a>
</footer>
</article>
</dialog>

</main>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>

<script>
const routes = <?= json_encode($routes, JSON_UNESCAPED_UNICODE); ?>;
const tbody = document.getElementById('routesBody');
const modal = document.getElementById('routeModal');
let map, polylineLayer;

/* RENDER TABLE */
routes.forEach(r => {
  const tr = document.createElement('tr');
  tr.dataset.route = JSON.stringify(r);

  tr.innerHTML = `
    <td>${r.name}</td>
    <td>${Number(r.distance_km).toFixed(2)}</td>
    <td>${r.elevation}</td>
    <td>${r.type}</td>
  `;

  tr.onclick = () => openModal(r);
  tbody.appendChild(tr);
});

/* MODAL LOGIC */
function openModal(r) {
  modal.showModal();

  document.getElementById('modalName').textContent = r.name;
  document.getElementById('modalMeta').innerHTML = `
    Distance: ${r.distance_km.toFixed(2)} km<br>
    Elevation: ${r.elevation} m<br>
    Type: ${r.type}
  `;
  document.getElementById('modalDesc').textContent = r.description || 'No description';
  document.getElementById('stravaLink').href = `https://www.strava.com/routes/${r.route_id}`;

  setTimeout(() => renderMap(r.summary_polyline), 100);
}

/* MAP */
function renderMap(polyline) {
  if (map) map.remove();

  map = L.map('map');
  const coords = polyline ? polylineDecode(polyline) : [];

  if (coords.length) {
    polylineLayer = L.polyline(coords).addTo(map);
    map.fitBounds(polylineLayer.getBounds());
  }

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
}

function initMap(route) {
  const mapId = `map-${route.route_id}`;
  const mapEl = document.getElementById(mapId);

  if (mapEl.dataset.loaded) return;

  const map = L.map(mapId);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap'
  }).addTo(map);

  if (route.summary_polyline) {
    const coords = polyline.decode(route.summary_polyline)
      .map(c => [c[0], c[1]]);

    const line = L.polyline(coords).addTo(map);
    map.fitBounds(line.getBounds());
  }

  map.invalidateSize();
  mapEl.dataset.loaded = "true";
}

    
function polylineDecode(str) {
  return polyline.decode(str).map(c => [c[0], c[1]]);
}

</script>
