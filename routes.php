<?php
/* ===============================
----Important rule going forward----
-Use case-                 -ID to use-
DB queries                 internal_user_id ✅
Strava API calls           strava_id
Session auth check         internal_user_id
================================ */
session_start();

ini_set('display_errors', 0); // Turned off on production screens to protect dynamic JSON injection pipelines
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background-color: #f8fafc;
  color: #1e293b;
  margin: 0;
  padding: 0;
  -webkit-font-smoothing: antialiased;
}


.container-premium {
  width: 100%;
  max-width: 100%; /* Removes the viewport walls */
  padding: 1.5rem 2rem; /* Tighter top/bottom gap, clean side breathing room */
  box-sizing: border-box;
}

/* --- UPPER ACTION HEADER DASHBOARD --- */
.dashboard-header-block {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2.5rem;
}

.title-area h1 {
  font-size: 1.85rem;
  font-weight: 800;
  color: #0f172a;
  letter-spacing: -0.03em;
  margin: 0 0 0.4rem 0;
}

.title-area p {
  color: #64748b;
  font-size: 0.95rem;
  font-weight: 500;
  margin: 0;
}

.profile-summary-tag {
  display: flex;
  align-items: center;
  gap: 12px;
  background: #ffffff;
  padding: 0.5rem 1rem 0.5rem 0.5rem;
  border-radius: 30px;
  border: 1px solid #e2e8f0;
}
.profile-summary-tag img {
  border-radius: 50%;
  object-fit: cover;
}

/* Premium Button Architecture */
.btn-premium-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background-color: #0f172a;
  color: #ffffff;
  font-size: 0.88rem;
  font-weight: 600;
  padding: 0.65rem 1.1rem;
  border-radius: 8px;
  border: 1px solid #0f172a;
  cursor: pointer;
  text-decoration: none;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
  transition: all 0.15s ease;
}

.btn-premium-action:hover {
  background-color: #1e293b;
  border-color: #1e293b;
  transform: translateY(-1px);
}

.btn-premium-secondary {
  background-color: #ffffff;
  color: #334155;
  border: 1px solid #cbd5e1;
}

.btn-premium-secondary:hover {
  background-color: #f8fafc;
  border-color: #94a3b8;
  color: #0f172a;
}

/* --- THE DATA CARRIAGE SHEET MODULE --- */
/* Ensure the wrapper card goes corner-to-corner seamlessly */
.premium-data-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 8px; /* Slightly tighter corner radius for wide views */
  box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
  overflow: hidden;
  margin-bottom: 2rem;
  width: 100%;
}

/* --- REFINED STABLE GRID LAYOUT --- */
.routesTable {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.92rem;
}


.routesTable th {
  background-color: #f8fafc;
  color: #475569;
  font-weight: 600;
  font-size: 0.74rem; /* Micro-typography looks crisper when compressed */
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 0.6rem 1rem; /* Dropped from 1rem to 0.6rem vertical padding */
  border-bottom: 1px solid #e2e8f0;
  user-select: none;
}

.routesTable td {
  padding: 0.55rem 1rem; /* Dropped from 1.1rem to 0.55rem vertical padding */
  border-bottom: 1px solid #f1f5f9;
  color: #334155;
  font-weight: 500;
  vertical-align: middle;
}

    

.routesTable th[data-sort] {
  cursor: pointer;
}
.routesTable th[data-sort]:hover {
  color: #0f172a;
  background-color: #f1f5f9;
}

.routesTable tbody tr.route-row {
  cursor: pointer;
  transition: background-color 0.15s ease;
}
.routesTable tbody tr.route-row:hover {
  background-color: #f8fafc;
}

/* Inline Collapsible Preview Canvas Container Module */
.details-row {
  background-color: #fafafa;
}
.details-row td {
  padding: 0 !important;
}
.route-details-box {
  padding: 1.75rem 2rem !important;
  border-bottom: 1px solid #cbd5e1;
}

/* Leaflet Layout Structuring Elements inside Row Details */
.route-layout {
  display: flex;
  gap: 24px;
}
.route-map-wrap {
  flex: 1;
  min-width: 0;
  height: 320px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid #cbd5e1;
}
.route-map {
  width: 100%;
  height: 100%;
}
.route-info {
  width: 380px;
  display: flex;
  flex-direction: column;
}
.route-info h4 {
  margin: 0 0 1rem 0;
  font-size: 1.2rem;
  font-weight: 700;
}
.route-info h4 a {
  color: #0f172a;
  text-decoration: none;
}
.route-info h4 a:hover {
  color: #0284c7;
}
.route-meta {
  list-style: none;
  padding: 0;
  margin: 0 0 1.5rem 0;
}
.route-meta li {
  padding: 0.5rem 0;
  border-bottom: 1px solid #e2e8f0;
  font-size: 0.88rem;
}
.route-tags label {
  display: block;
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: uppercase;
  color: #64748b;
  margin-bottom: 0.4rem;
}
.route-tags input {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  font-family: inherit;
  font-size: 0.88rem;
}

/* --- PREMIUM GRAPHIC MICRO BADGES --- */
.route-discipline-badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 6px; /* Tighter padding boundaries */
  border-radius: 4px;
  font-size: 0.72rem; /* Slightly smaller font balance */
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}
.badge-discipline-1 { background-color: #eff6ff; color: #1d4ed8; }
.badge-discipline-6 { background-color: #fef3c7; color: #b45309; }
.badge-discipline-2 { background-color: #ecfdf5; color: #047857; }
.badge-discipline-3 { background-color: #f5f5f4; color: #44403c; }

.stat-numeric-bold {
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  color: #0f172a;
}
.stat-unit-label {
  font-size: 0.8rem;
  color: #64748b;
  font-weight: 500;
  margin-left: 2px;
}
</style>

<div class="container-premium">

  <div class="dashboard-header-block">
    <div class="title-area">
      <h1>My Saved Routes</h1>
      <div style="display:flex; align-items:center; gap:12px; margin-top:0.4rem;">
        <div class="profile-summary-tag">
          <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" width="32" height="32">
          <span style="font-size:0.88rem; font-weight:600; color:#334155;">
            <?= htmlspecialchars($user['firstname'].' '.$user['lastname']) ?>
          </span>
        </div>
        <p style="font-size:0.85rem; color:#64748b;">
          Last Strava Sync: <span style="font-weight:600; color:#475569;"><?= $user['last_routes_sync'] ? htmlspecialchars($user['last_routes_sync']) : 'Never' ?></span>
        </p>
      </div>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
      <a id="mapLink" href="map.php" class="btn-premium-action btn-premium-secondary">Map View Map 🗺️</a>
      <button id="openFilters" class="btn-premium-action btn-premium-secondary" type="button">Filters Panel ⚙️</button>
      <button id="fetchRoutes" class="btn-premium-action" type="button">Sync Strava Tracks</button>
    </div>  
  </div>

  <div class="premium-data-card">
    <table class="routesTable">
      <thead>
        <tr>
          <th data-sort="name" style="width: 35%;">Name</th>
          <th data-sort="type" style="width: 12%;">Type</th>
          <th data-sort="distance_km" style="width: 13%;">Distance</th>
          <th data-sort="elevation" style="width: 13%;">Elevation Change</th>
          <th data-sort="estimated_moving_time" style="width: 12%;">Moving Time</th>
          <th data-sort="created_date" style="width: 15%;">Creation Date</th>
          <th style="width: 5%; text-align: center;">Status</th>
        </tr>
      </thead>
      <tbody id="routesBody"></tbody>
    </table>
  </div>

  <?php include 'filter_panel.php'; ?>   
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>
<script src="routes_shared.js?v=1.0.1"></script>
    
<script>
var routes = <?= json_encode($routes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]'; ?>;
    
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
        tbody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 3rem; color: #64748b; font-weight:500;">No routes found matching your active filter configuration.</td></tr>`;
        return;
    }

    data.forEach(route => {
        const row = document.createElement('tr');
        row.className = 'route-row';
        
        // Status indicator parsing matching style context configurations
        let statusString = '';
        if (route.starred == 1) statusString += '<span style="color:#f59e0b; margin-right:4px;">★</span>';
        if (route.private == 1) statusString += '<span style="color:#64748b;">🔒</span>';
        if (!statusString) statusString = '<span style="color:#cbd5e1;">—</span>';

        row.innerHTML = `
            <td style="font-weight: 600; color: #0f172a;">${route.name || 'Untitled Track'}</td>
            <td>
                <span class="route-discipline-badge badge-discipline-${route.type || 1}">
                    ${routeTypeLabel(route.type)}
                </span>
            </td>
            <td><span class="stat-numeric-bold">${route.distance_km ? Number(route.distance_km).toFixed(1) : '0.0'}</span><span class="stat-unit-label">km</span></td>
            <td><span class="stat-numeric-bold">${Math.round(route.elevation) || 0}</span><span class="stat-unit-label">m</span></td>
            <td style="font-variant-numeric: tabular-nums; font-weight:500;">${formatDuration(route.estimated_moving_time)}</td>
            <td style="color: #64748b; font-size: 0.88rem;">${route.created_date || '—'}</td>
            <td style="text-align: center; font-size: 1rem;">${statusString}</td>
        `;

        const details = document.createElement('tr');
        details.hidden = true;
        details.className = 'details-row';
        details.innerHTML = `<td colspan="7" class="route-details-box"><div id="details-content-${route.route_id}">Loading Canvas Frame Preview Layer...</div></td>`;

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
                                <li><strong>Distance Boundary:</strong> ${Number(route.distance_km).toFixed(2)} km</li>
                                <li><strong>Net Elevation:</strong> ${Math.round(route.elevation)} meters</li>
                                <li><strong>Activity Profile:</strong> ${routeTypeLabel(route.type)}</li>
                                <li><strong>Regional Origin:</strong> ${route.country || 'Undefined Region'}</li>
                            </ul>
                            <div class="route-tags">
                                <label>Track Classification Tags</label>
                                <input type="text" value="${route.tags || ''}" placeholder="e.g. weekend, gravel, climbing..." onblur="saveTags('${route.route_id}', this.value)">
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
    
    btn.disabled = true;

    let page = 1;
    let keepGoing = true;
    let totalSynced = 0;

    try {
        while (keepGoing) {
            btn.innerText = `Syncing page ${page}... (${totalSynced} tracks)`;

            const res = await fetch(`fetch_routes.php?page=${page}`);
            const text = await res.text();
            
            let data;
            try {
                data = JSON.parse(text);
            } catch (jsonErr) {
                console.error('Invalid JSON response:', text);
                throw new Error('Server returned an invalid response syntax layer.');
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

        btn.innerText = "Sync complete! Resolving geography tags...";
        
        let geocodingDone = false;
        let totalFixed = 0;

        while (!geocodingDone) {
            try {
                const geoRes = await fetch('sync_countries.php'); 
                const geoData = await geoRes.json();

                if (geoData.updated_count > 0) {
                    totalFixed += geoData.updated_count;
                    btn.innerText = `Geocoding... (${totalFixed} paths localized)`;
                    await new Promise(r => setTimeout(r, 500));
                } else {
                    geocodingDone = true;
                }
            } catch (err) {
                console.warn("Geocoding batch skipped or hit maximum capacity boundaries.", err);
                geocodingDone = true; 
            }
        }

        btn.innerText = `Success! ${totalSynced} structural updates completed.`;
        setTimeout(() => {
            location.reload(); 
        }, 1200);

    } catch (e) {
        console.error('Fetch operation abort error:', e);
        alert('Error: ' + e.message);
        btn.innerText = originalText;
    } finally {
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
      alert(data.error || 'Failed to preserve active track classification strings.');
    }
  } catch (e) {
    alert('Error archiving tag array structure modification adjustments.');
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

        if (typeof applyFilters === 'function') {
            applyFilters();
        } else {
            renderTable(routes);
        }
    });
});
</style>

<?php include 'footer.php'; ?>
<?php exit(0); ?>
