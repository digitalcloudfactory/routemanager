<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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
    SELECT firstname, lastname, avatar
    FROM users
    WHERE strava_id = ?
");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

/* ===============================
   LOAD ROUTES (PER USER)
================================ */

$stmt = $pdo->prepare("
    SELECT
        route_id,
        name,
        description,
        distance_km,
        elevation,
        type,
        summary_polyline
    FROM strava_routes
    WHERE user_id = ?
    ORDER BY updated_at DESC
");
$stmt->execute([$user_id]);
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include 'header.php'; ?>

<style>
tr.route-row { cursor: pointer; }
.route-details article { margin-top: 1rem; }
.route-map { height: 300px; border-radius: 12px; }
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
      <small>Strava athlete</small>
    </div>
  </div>
</header>

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

</main>

<?php include 'footer.php'; ?>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>

<script>
const routes = <?= json_encode($routes, JSON_UNESCAPED_UNICODE); ?>;
const tbody = document.getElementById('routesBody');

/* ===============================
   RENDER TABLE + INLINE DETAILS
================================ */

routes.forEach(route => {

  const row = document.createElement('tr');
  row.className = 'route-row';

  row.innerHTML = `
    <td>${route.name}</td>
    <td>${Number(route.distance_km).toFixed(2)}</td>
    <td>${route.elevation}</td>
    <td>${route.type}</td>
  `;

  const details = document.createElement('tr');
  details.className = 'route-details';
  details.hidden = true;

  details.innerHTML = `
    <td colspan="4">
      <article>
        <p><strong>Description</strong><br>
           ${route.description || 'No description provided'}
        </p>

        <p>
          <strong>Distance:</strong> ${route.distance_km.toFixed(2)} km<br>
          <strong>Elevation:</strong> ${route.elevation} m<br>
          <strong>Type:</strong> ${route.type}
        </p>

        <div id="map-${route.route_id}" class="route-map"></div>

        <p>
          <a href="https://www.strava.com/routes/${route.route_id}"
             target="_blank"
             role="button">
            Open on Strava
          </a>
        </p>
      </article>
    </td>
  `;

  row.addEventListener('click', () => {
    details.hidden = !details.hidden;
    if (!details.hidden) initMap(route);
  });

  tbody.appendChild(row);
  tbody.appendChild(details);
});

/* ===============================
   LEAFLET MAP INIT (VISIBLE ONLY)
================================ */

function initMap(route) {
  const mapId = `map-${route.route_id}`;
  const el = document.getElementById(mapId);

  if (!el || el.dataset.loaded) return;

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
  el.dataset.loaded = "true";
}
</script>

</body>
</html>
