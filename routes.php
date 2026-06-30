<?php
/* ===============================
----Important rule going forward----
-Use case-                 -ID to use-
DB queries                 internal_user_id ✅
Strava API calls           strava_id
Session auth check         internal_user_id
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
    SELECT firstname, lastname, avatar, last_routes_sync
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
        country,
        starred,
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
   Attach tags to routes
================================ */
foreach ($routes as &$route) {
    $route['tags'] = $tagsByRoute[$route['route_id']] ?? '';
}
unset($route);

$countryStmt = $pdo->prepare("SELECT DISTINCT country FROM strava_routes WHERE user_id = ? AND country IS NOT NULL AND country != '' ORDER BY country ASC");
$countryStmt->execute([$internalUserId]);
$countries = $countryStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
body {
  font-family: 'Inter', sans-serif;
  background-color: #f4f6f9;
  color: #1e293b;
}

main.container {
  max-width: 100%;
  padding: 1.5rem 2.5rem;
  box-sizing: border-box;
}

/* Modern App Header Profile Dashboard Section Control Bar Look */
header.grid {
  background: #ffffff;
  padding: 1rem 1.5rem !important;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px rgba(148, 163, 184, 0.08);
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 2rem;
}

.user-profile-block {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-profile-block img {
  border: 2px solid #0284c7;
  padding: 2px;
  background: #ffffff;
}

.user-profile-block strong {
  color: #0f172a;
  font-size: 0.95rem;
}

.user-profile-block small {
  color: #64748b;
  font-size: 0.8rem;
}

.actions-block {
  display: flex;
  gap: 8px;
  align-items: center;
}

/* Base Form Buttons Elements definitions */
.btn-custom {
  font-family: 'Inter', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  text-decoration: none !important;
  display: inline-flex;
  align-items: center;
  transition: all 0.2s;
}

.btn-custom:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #94a3b8;
}

.btn-custom.primary {
  background: #0284c7;
  color: #ffffff;
  border: none;
}

.btn-custom.primary:hover {
  background: #0369a1;
}

/* Table Design Modernization */
tr.route-row { 
  cursor: pointer;
  transition: background-color 0.15s ease;
}
tr.route-row:hover {
  background-color: #f8fafc !important;
}

.routesTable {
  font-size: 0.85rem;
  width: 100%;
  table-layout: auto;
  background: #ffffff;
  border-collapse: separate;
  border-spacing: 0;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 15px rgba(148, 163, 184, 0.05);
  overflow: hidden;
}

.routesTable th,
.routesTable td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}

.routesTable th {
  background-color: #f8fafc;
  font-weight: 600;
  color: #475569;
  border-bottom: 2px solid #e2e8f0;
}

.routesTable th[data-sort] {
  cursor: pointer;
  user-select: none;
}

.routesTable th[data-sort]:after {
  content: ' ↕';
  opacity: 0.3;
  font-size: 0.75rem;
  margin-left: 4px;
}

.routesTable th.sort-asc:after { content: ' ↑'; opacity: 1; color: #0284c7; }
.routesTable th.sort-desc:after { content: ' ↓'; opacity: 1; color: #0284c7; }

/* Inline Table Dropdown Preview Styling */
.route-details-box {
  background-color: #f8fafc;
  padding: 1.5rem !important;
  border-bottom: 1px solid #e2e8f0;
}

.route-layout {
  display: flex;
  gap: 1.5rem;
}

.route-map-wrap {
  flex: 3;
  min-width: 0;
}

.route-map {
  height: 400px;
  border-radius: 8px;
  border: 1px solid #cbd5e1;
  box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.route-info {
  flex: 1;
  font-size: 0.8rem;
  line-height: 1.5;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.route-info h4 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
}

.route-info h4 a {
  color: #0284c7;
  text-decoration: none;
}
.route-info h4 a:hover { text-decoration: underline; }

.route-meta {
  list-style: none;
  padding: 0;
  margin: 0;
}

.route-meta li {
  margin-bottom: 0.4rem;
  color: #475569;
}

.route-meta strong { color: #0f172a; }

.route-tags strong {
  display: block;
  margin-bottom: 0.25rem;
  color: #0f172a;
}

.route-tags input {
  width: 100%;
  padding: 0.4rem 0.6rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  font-size: 0.8rem;
  background-color: #ffffff;
}
.route-tags input:focus {
  outline: none;
  border-color: #0284c7;
}

#filterPanel {
  position: fixed;
  top: 0;
  right: 0;
  width: 360px;
  height: 100%;
  background: #ffffff;
  box-shadow: -4px 0 24px rgba(148, 163, 184, 0.15);
  border-left: 1px solid #e2e8f0;
  padding: 2rem 1.5rem;
  transform: translateX(100%);
  transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  z-index: 1050;
  overflow-y: auto;
}

#filterPanel.open {
  transform: translateX(0);
}

.distance-label {
  background-color: #ffffff;
  color: #1e293b;
  font-size: 0.6rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 10px;
  border: 1px solid #cbd5e1;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

figure {
  width: 100%;
  overflow-x: auto;
  margin: 0;
}

.range-slider input[type="range"]::-webkit-slider-thumb { pointer-events: auto; cursor: pointer; }
.range-slider input[type="range"]::-moz-range-thumb { pointer-events: auto; cursor: pointer; }
#filterDistanceMin { background: transparent !important; }        
</style>

<body>

<main class="container">

<header class="grid">
  <div class="user-profile-block">
    <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" width="48" height="48" style="border-radius:50%">
    <div>
      <strong><?= htmlspecialchars($user['firstname'].' '.$user['lastname']) ?></strong><br>
      <small>Last Strava Sync: <?= $user['last_routes_sync'] ? htmlspecialchars($user['last_routes_sync']) : 'Never synced' ?></small>
    </div>
  </div>

  <div class="actions-block">
    <a id="mapLink" href="map.php" class="btn-custom">Map view</a>
    <button id="fetchRoutes" class="btn-custom primary" type="button">Fetch new routes from Strava</button>
    <button id="openFilters" class="btn-custom" type="button">Filters ⚙️</button>
  </div>    
</header>

<section>
<figure>
<table class="routesTable text-center">
<thead>
<tr>
    <th data-sort="name">Name</th>
    <th data-sort="distance_km">Distance (km)</th>
    <th data-sort="elevation">Elevation (m)</th>
    <th data-sort="estimated_moving_time">Moving Time</th>
    <th data-sort="created_date">Creation Date</th>
    <th data-sort="starred">Starred</th>
    <th data-sort="private">Private</th>
  </tr>
</thead>
<tbody id="routesBody"></tbody>
</table>
</figure>
</section>
  <?php include 'filter_panel.php'; ?>   
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>

<script>
var routes = <?= json_encode($routes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]'; ?>;
const tbody = document.getElementById('routesBody');
    
function formatDuration(seconds) {
    if (!seconds) return "0:00:00";
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

async function renderTable(data) {
    const tbody = document.getElementById('routesBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (!data || data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 2rem; color: #64748b;">No routes found matching filter criteria.</td></tr>`;
        return;
    }

    data.forEach(route => {
        const row = document.createElement('tr');
        row.className = 'route-row';
        row.innerHTML = `
            <td style="font-weight: 500; color: #0f172a;">${route.name || 'Untitled'}</td>
            <td>${route.distance_km ? Number(route.distance_km).toFixed(2) : '0.00'} km</td>
            <td>${route.elevation || 0} m</td>
            <td>${formatDuration(route.estimated_moving_time)}</td>
            <td>${route.created_date}</td>
            <td style="color: #f59e0b; font-size: 1.1rem; text-align: center;">${route.starred == 1 ? '★' : ''}</td>
            <td style="text-align: center;">${route.private == 1 ? '🔒' : ''}</td>
        `;

        const details = document.createElement('tr');
        details.hidden = true;
        details.className = 'details-row';
        details.innerHTML = `<td colspan="7" class="route-details-box"><div id="details-content-${route.route_id}">Loading Preview Canvas...</div></td>`;

        row.onclick = async () => {
            details.hidden = !details.hidden;
            if (!details.hidden) {
                const container = document.getElementById(`details-content-${route.route_id}`);
                
                container.innerHTML = `
                    <div class="route-layout">
                        <div class="route-map-wrap"><div id="map-${route.route_id}" class="route-map"></div></div>
                        <div class="route-info">
                            <h4><a href="https://www.strava.com/routes/${route.route_id}" target="_blank">${route.name}</a></h4>
                            <ul class="route-meta">
                                <li><strong>Distance:</strong> ${Number(route.distance_km).toFixed(2)} km</li>
                                <li><strong>Elevation:</strong> ${route.elevation} m</li>
                                <li><strong>Type:</strong> ${routeTypeLabel(route.type)}</li>
                            </ul>
                            <div class="route-tags">
                                <strong>Tags</strong>
                                <input type="text" value="${route.tags || ''}" placeholder="Add comma-separated tags..." onblur="saveTags('${route.route_id}', this.value)">
                            </div>
                        </div>
                    </div>`;

                if (!route.summary_polyline) {
                    try {
                        const res = await fetch(`get_polyline.php?route_id=${route.route_id}`);
                        const polyData = await res.json();
                        route.summary_polyline = polyData.polyline;
                    } catch (e) { console.error("Polyline dynamic data load failure", e); }
                }

                if (route.summary_polyline) {
                    setTimeout(() => initMap(route), 50);
                }
            }
        };
        tbody.appendChild(row);
        tbody.appendChild(details);
    });
}

function addDistanceMarkers(map, latlngs, stepKm = 10) {
  let distance = 0;
  let nextMarker = stepKm;

  for (let i = 1; i < latlngs.length; i++) {
    distance += haversineDistance(latlngs[i - 1], latlngs[i]);

    if (distance >= nextMarker) {
      const marker = L.circleMarker(latlngs[i], {
        radius: 4,
        color: '#475569',
        fillColor: '#ffffff',
        fillOpacity: 1,
        weight: 1.5
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
    if (!el || el.dataset.loaded || !route.summary_polyline) return;

    try {
        const coords = polyline.decode(route.summary_polyline).map(c => [c[0], c[1]]);
        const map = L.map(mapId);
        
        // Match Positron Light Mode Style
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap &copy; CARTO'
        }).addTo(map);

        const line = L.polyline(coords, { color: '#0284c7', weight: 4 }).addTo(map);
        map.fitBounds(line.getBounds());
        map.invalidateSize();
        el.dataset.loaded = "true";
        if (typeof addDistanceMarkers === 'function') addDistanceMarkers(map, coords, 10);
    } catch (err) {
        console.error("Leaflet target runtime engine failure:", err);
    }
}

document.getElementById('fetchRoutes').addEventListener('click', async () => {
    const btn = document.getElementById('fetchRoutes');
    const originalText = btn.innerText;
    
    btn.setAttribute('aria-busy', 'true');
    btn.disabled = true;

    let page = 1;
    let keepGoing = true;
    let totalSynced = 0;

    try {
        while (keepGoing) {
            btn.innerText = `Syncing page ${page}... (${totalSynced} routes)`;

            const res = await fetch(`fetch_routes.php?page=${page}`);
            const text = await res.text();
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (jsonErr) {
                console.error('Invalid JSON response:', text);
                throw new Error('Server returned an invalid response.');
            }

            if (!data.success) {
                throw new Error(data.error || 'Failed to fetch batch');
            }

            totalSynced += data.routes_in_batch;
            
            if (data.has_more) {
                page++;
                await new Promise(resolve => setTimeout(resolve, 1000));
            } else {
                keepGoing = false;
            }
        }

        btn.innerText = "Sync complete! Filling in country data...";
        
        let geocodingDone = false;
        let totalFixed = 0;

        while (!geocodingDone) {
            try {
                const geoRes = await fetch('sync_countries.php'); 
                const geoData = await geoRes.json();

                if (geoData.updated_count > 0) {
                    totalFixed += geoData.updated_count;
                    btn.innerText = `Geocoding... (${totalFixed} countries fixed)`;
                    await new Promise(r => setTimeout(r, 500));
                } else {
                    geocodingDone = true;
                }
            } catch (err) {
                console.warn("Geocoding batch failed.", err);
                geocodingDone = true; 
            }
        }

        btn.innerText = `All Done! ${totalSynced} synced, ${totalFixed} geocoded.`;
        setTimeout(() => {
            location.reload(); 
        }, 1500);

    } catch (e) {
        console.error('Fetch error:', e);
        alert('Error: ' + e.message);
        btn.innerText = originalText;
    } finally {
        btn.removeAttribute('aria-busy');
        btn.disabled = false;
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

function haversineDistance(a, b) {
  const R = 6371; 
  const dLat = (b[0] - a[0]) * Math.PI / 180;
  const dLng = (b[1] - a[1]) * Math.PI / 180;
  const lat1 = a[0] * Math.PI / 180;
  const lat2 = b[0] * Math.PI / 180;
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}
    
async function saveTags(routeId, value) {
  const tags = value.split(',').map(t => t.trim()).filter(Boolean);

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

<script>
const mapLink = document.getElementById('mapLink');

function updateMapLinkFromURL() {
    if (!mapLink) return;
    mapLink.href = 'map.php' + window.location.search;
}

updateMapLinkFromURL();
window.addEventListener('popstate', updateMapLinkFromURL);

const originalReplace = history.replaceState;
history.replaceState = function (...args) {
    originalReplace.apply(this, args);
    updateMapLinkFromURL();
};

document.addEventListener('DOMContentLoaded', () => {
    renderTable(routes);
});

let currentSort = { column: null, direction: 'asc' };

document.querySelectorAll('.routesTable th[data-sort]').forEach(th => {
    th.addEventListener('click', () => {
        const column = th.dataset.sort;
        
        if (currentSort.column === column) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = column;
            currentSort.direction = 'asc';
        }

        document.querySelectorAll('.routesTable th').forEach(el => el.classList.remove('sort-asc', 'sort-desc'));
        th.classList.add(currentSort.direction === 'asc' ? 'sort-asc' : 'sort-desc');

        routes.sort((a, b) => {
            let valA = a[column];
            let valB = b[column];

            if (!isNaN(parseFloat(valA)) && isFinite(valA)) {
                valA = parseFloat(valA);
                valB = parseFloat(valB);
            } else {
                valA = (valA || '').toString().toLowerCase();
                valB = (valB || '').toString().toLowerCase();
            }

            if (valA < valB) return currentSort.direction === 'asc' ? -1 : 1;
            if (valA > valB) return currentSort.direction === 'asc' ? 1 : -1;
            return 0;
        });

        applyFilters();
    });
});
</style>
    
<script src="routes_shared.js?v=1.0.1"></script>

<?php include 'footer.php'; ?>
<?php exit(0); ?>
