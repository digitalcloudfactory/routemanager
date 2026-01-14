
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
<script src="https://unpkg.com/@mapbox/polyline"></script>

<style>
tr.route-row { cursor: pointer; }
.route-details article { margin-top: 1rem; }
.route-map { height: 300px; border-radius: 12px; }

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

<section class="grid">
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

<aside id="filterPanel" aria-hidden="true">
  <article>
    <header class="grid">
      <strong>Filters</strong>
      <a href="#" aria-label="Close" onclick="toggleFilters(false)"></a>
    </header>

    <label>
      Name
      <input id="filterName" type="text" placeholder="Route name">
    </label>

    <label>
      Min distance (km)
      <input id="filterDistance" type="number" min="0" step="0.1">
    </label>

    <label>
      Min elevation (m)
      <input id="filterElevation" type="number" min="0">
    </label>

    <label>
      Type
      <select id="filterType">
        <option value="">All</option>
        <option value="1">Ride</option>
        <option value="2">Run</option>
        <option value="3">Walk</option>
      </select>
    </label>

    <footer>
      <button class="secondary" onclick="clearFilters()">Clear</button>
    </footer>
  </article>
</aside>


    
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

function renderTable(data) {
  tbody.innerHTML = '';

  if (data.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" style="text-align:center">
          No routes match the filters
        </td>
      </tr>
    `;
    return;
  }

  data.forEach(route => {
    const row = document.createElement('tr');
    row.className = 'route-row';

    row.innerHTML = `
      <td>${route.name}</td>
      <td>${Number(route.distance_km).toFixed(2)}</td>
      <td>${route.elevation}</td>
      <td>${routeTypeLabel(route.type)}</td>
    `;

    const details = document.createElement('tr');
    details.hidden = true;

    details.innerHTML = `
      <td colspan="4">
        <article>
          <p><strong>Description</strong><br>
            ${route.description || 'No description'}
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

    row.onclick = () => {
      details.hidden = !details.hidden;
      if (!details.hidden) initMap(route);
    };

    tbody.appendChild(row);
    tbody.appendChild(details);
  });
}



/* INITIAL RENDER */
renderTable(routes);


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

addDistanceMarkers(map, coords, 10);
}

function addDistanceMarkers(map, latlngs, stepKm = 10) {
  let distance = 0;
  let nextMarker = stepKm;

  for (let i = 1; i < latlngs.length; i++) {
    distance += haversineDistance(latlngs[i - 1], latlngs[i]);

    if (distance >= nextMarker) {
      const marker = L.circleMarker(latlngs[i], {
        radius: 4,
        color: '#666',
        fillColor: '#fff',
        fillOpacity: 1,
        weight: 2
      }).addTo(map);

      marker.bindTooltip(`${nextMarker} km`, {
        permanent: true,
        direction: 'top',
        className: 'distance-label'
      });

      nextMarker += stepKm;
    }
  }
}
    
/* ===============================
   FETCH ROUTES (AJAX)
================================ */

document.getElementById('fetchRoutes').addEventListener('click', async () => {
  const btn = document.getElementById('fetchRoutes');
  btn.setAttribute('aria-busy', 'true');

  try {
    const res = await fetch('fetch_routes.php');
    const data = await res.json();

    if (!data.success) {
      alert(data.error || 'Failed to fetch routes');
      return;
    }

    location.reload();
  } catch (e) {
    alert('Error fetching routes');
  } finally {
    btn.removeAttribute('aria-busy');
  }
});

let filteredRoutes = [...routes];

/* ===============================
   FILTER PANEL TOGGLE
================================ */

const panel = document.getElementById('filterPanel');
document.getElementById('openFilters').onclick = () => toggleFilters(true);

function toggleFilters(open) {
  panel.classList.toggle('open', open);
  panel.setAttribute('aria-hidden', !open);
}

/* ===============================
   FILTER LOGIC
================================ */

function applyFilters() {
  const name = document.getElementById('filterName').value.toLowerCase();
  const minDist = parseFloat(document.getElementById('filterDistance').value) || 0;
  const minElev = parseFloat(document.getElementById('filterElevation').value) || 0;
  const type = document.getElementById('filterType').value;

  filteredRoutes = routes.filter(r =>
    r.name.toLowerCase().includes(name) &&
    r.distance_km >= minDist &&
    r.elevation >= minElev &&
    (!type || Number(r.type) === Number(type))
  );

  renderTable(filteredRoutes);
}

function clearFilters() {
  document.getElementById('filterName').value = '';
  document.getElementById('filterDistance').value = '';
  document.getElementById('filterElevation').value = '';
  document.getElementById('filterType').value = '';
  applyFilters();
}

/* ===============================
   EVENTS
================================ */

['filterName','filterDistance','filterElevation','filterType']
  .forEach(id => {
    document.getElementById(id).addEventListener('input', applyFilters);
  });


const filterBtn = document.getElementById('openFilters');

filterBtn.onclick = () => {
  const isOpen = panel.classList.contains('open');
  toggleFilters(!isOpen);
};

function toggleFilters(open) {
  panel.classList.toggle('open', open);
  panel.setAttribute('aria-hidden', !open);
}

function routeTypeLabel(type) {
  return {
    1: 'Ride',
    2: 'Run',
    3: 'Walk'
  }[type] || 'Other';
}

/* ===============================
 Map Distance markers
================================ */
function haversineDistance(a, b) {
  const R = 6371; // km
  const dLat = (b[0] - a[0]) * Math.PI / 180;
  const dLng = (b[1] - a[1]) * Math.PI / 180;

  const lat1 = a[0] * Math.PI / 180;
  const lat2 = b[0] * Math.PI / 180;

  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(lat1) * Math.cos(lat2) *
    Math.sin(dLng / 2) ** 2;

  return 2 * R * Math.asin(Math.sqrt(h));
}

    
</script>

