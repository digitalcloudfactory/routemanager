<?php
/* ===============================
----Important rule going forward----
-Use case-	            -ID to use-
DB queries	            internal_user_id ✅
Strava API calls	    strava_id
Session auth check	    internal_user_id
================================ */
session_start();

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
        description,
        distance_km,
        elevation,
        type,
        estimated_moving_time,
        private,
        starred,
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


<style>
tr.route-row { cursor: pointer;}
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

.distance-label {
  background-color: #fff;       /* White background for visibility */
  color: #333;                  /* Dark text */
  font-size: 0.55rem;           /* Smaller font */
  font-weight: 600;
  padding: 2px 4px;             /* Small padding */
  border-radius: 50%;           /* Round pill */
  border: 1px solid #ccc;       /* Optional subtle border */
  box-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.routesTable {
  font-size: 0.8rem;
  width: 100%;        /* take full available width */
  table-layout: auto; /* let columns size naturally */
}

.routesTable th,
.routesTable td {
  padding: 0.3rem 0.5rem;
}

.routesTable th {
  font-weight: 600;
}

    .route-details {
  margin-top: 0.5rem;
}

.route-layout {
  display: flex;
  gap: 1rem;
  align-items: stretch;
}

/* LEFT: MAP */
.route-map-wrap {
  flex: 3;               /* ≈ 75% */
  min-width: 0;
}

.route-map {
  height: 420px;
  border-radius: 12px;
  border: 1px solid #ddd;
}

/* RIGHT: INFO */
.route-info {
  flex: 1;               /* ≈ 25% */
  font-size: 0.75rem;
  line-height: 1.4;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.route-info h4 {
  margin: 0;
  font-size: 0.9rem;
}

.route-meta {
  list-style: none;
  padding: 0;
  margin: 0;
}

.route-meta li {
  margin-bottom: 0.25rem;
}

.route-description,
.route-tags {
  font-size: 0.7rem;
}

.route-tags input {
  width: 100%;
  font-size: 0.7rem;
}

main.container {
  max-width: 100%;    /* override container width */
  padding: 1rem 2rem; /* some horizontal padding */
  box-sizing: border-box;
}


/* Optional: ensure figure scrolls if table is wider than screen */
figure {
  width: 100%;
  overflow-x: auto;
  margin: 0;          /* remove default margins */
}

:root {
  --pico-font-size: 0.85rem;
  --pico-spacing: 0.5rem;
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
    <a id="mapLink" href="map.php">Map view</a>
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



<section>
<figure>
<table class="routesTable striped hover">
<thead>
<tr>
  <th>Name</th>
  <th>Distance (km)</th>
  <th>Elevation (m)</th>
  <th>Estimated Moving Time</th>
  <th>Private</th>
  <th>Starred</th>
</tr>
</thead>
<tbody id="routesBody"></tbody>
</table>
</figure>
</section>
  <?php include 'filter_panel.php'; ?>  
</main>



<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>

<script>
const routes = <?= json_encode($routes, JSON_UNESCAPED_UNICODE); ?>;
const tbody = document.getElementById('routesBody');
    
/* ===============================
   RENDER Seconds to Exact Moving time
================================ */

function formatDuration(seconds) {
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  return `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
}
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
      <td>${formatDuration(route.estimated_moving_time)}</td>
      <td>${route.starred}</td>
      <td>${route.private}</td>
    `;

    const details = document.createElement('tr');
    details.hidden = true;

    details.innerHTML = `
 <td colspan="4">
    <article class="route-details">
      <div class="route-layout">

        <!-- LEFT: MAP (≈75%) -->
        <div class="route-map-wrap">
          <div id="map-${route.route_id}" class="route-map"></div>
        </div>

        <!-- RIGHT: DETAILS (≈25%) -->
        <div class="route-info">
         <h4><a href="https://www.strava.com/routes/${route.route_id}" target="_blank">${route.name} </a></h4>

          <ul class="route-meta">
            <li><strong>Distance:</strong> ${Number(route.distance_km).toFixed(2)} km</li>
            <li><strong>Elevation:</strong> ${route.elevation} m</li>
            <li><strong>Moving Time:</strong> ${formatDuration(route.estimated_moving_time)}</li>
            <li><strong>Created:</strong> ${route.created_date}</li>
            <li><strong>Type:</strong> ${routeTypeLabel(route.type)}</li>
            <li><strong>Route ID:</strong> ${route.route_id}</li>
          </ul>

          <div class="route-description">
            <strong>Description</strong><br>
            ${route.description || '<em>No description</em>'}
          </div>

          <div class="route-tags">
            <strong>Tags</strong><br>
            <input type="text"
                   value="${route.tags || ''}"
                   placeholder="e.g. Gravel, Mallorca, Favorite"
                   onblur="saveTags('${route.route_id}', this.value)">
            <small>Comma separated</small>
          </div>

        </div>

      </div>
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

function addDistanceMarkers(map, latlngs, stepKm = 10) {
  let distance = 0;
  let nextMarker = stepKm;

  for (let i = 1; i < latlngs.length; i++) {
    distance += haversineDistance(latlngs[i - 1], latlngs[i]);

    if (distance >= nextMarker) {
      const marker = L.circleMarker(latlngs[i], {
        radius: 3,
        color: '#666',
        fillColor: '#fff',
        fillOpacity: 1,
        weight: 1
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
    
function initMap(route) {
  const mapId = `map-${route.route_id}`;
  const el = document.getElementById(mapId);
 const coords = polyline.decode(route.summary_polyline);
    
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


    
/* ===============================
   FETCH ROUTES (AJAX)
================================ */

document.getElementById('fetchRoutes').addEventListener('click', async () => {
  const btn = document.getElementById('fetchRoutes');
  btn.setAttribute('aria-busy', 'true');

  try {
    const res = await fetch('fetch_routes.php');
    const text = await res.text();  // read raw text first
    console.log('RAW response:', text);  // <-- log it
    const data = JSON.parse(text);       // then parse JSON

    if (!data.success) {
      alert(data.error || 'Failed to fetch routes');
      return;
    }

    location.reload();
  } catch (e) {
    console.error('Fetch error:', e);
    alert('Error fetching routes. Check console for details.');
  } finally {
    btn.removeAttribute('aria-busy');
  }
});

function routeTypeLabel(type) {
  return {
    1: 'Ride',
    2: 'Run',
    3: 'Walk',
    6: 'Gravel'
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
    

    

async function saveTags(routeId, value) {
  const tags = value
    .split(',')
    .map(t => t.trim())
    .filter(Boolean);

  try {
    const res = await fetch('save_route_tags.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ route_id: routeId, tags })
    });

    const data = await res.json();
    if (!data.success) {
      alert(data.error || 'Failed to save tags');
    }
  } catch (e) {
    alert('Error saving tags');
  }
}
    
</script>

<script src="routes_shared.js?v=1.0.1"></script>

  <script>
  const mapLink = document.getElementById('mapLink');

  function updateMapLinkFromURL() {
    if (!mapLink) return;
    mapLink.href = 'map.php' + window.location.search;
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
