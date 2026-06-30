<?php
/* ===============================
----Important rule going forward----
-Use case-                 -ID to use-
DB queries                 internal_user_id ✅
Strava API calls           strava_id
Session auth check         internal_user_id
================================ */
session_start();

ini_set('display_errors', 0); 
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

<style>
body {
    font-family: 'Inter', sans-serif;
    background-color: #f4f6f9;
    color: #1e293b;
    margin: 0;
    padding: 0;
    overflow: hidden; 
    -webkit-font-smoothing: antialiased;
}

/* Master Grid Workspace Frame */
.app-workspace-frame {
    display: flex;
    width: 100vw;
    height: 100vh;
    box-sizing: border-box;
    position: relative;
}

/* --- CENTER DATA PLATFORM PANEL (THE PURE TABLE VIEW) --- */
.center-data-platform {
    display: flex;
    flex: 1.3;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    flex-direction: column;
    z-index: 15;
    box-shadow: 6px 0 24px rgba(15, 23, 42, 0.02);
}

.platform-table-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.user-meta-bar {
    display: flex;
    align-items: center;
    gap: 12px;
}

.profile-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f8fafc;
    padding: 0.35rem 0.75rem 0.35rem 0.35rem;
    border-radius: 50px;
    border: 1px solid #e2e8f0;
}
.profile-badge img {
    border-radius: 50%;
    object-fit: cover;
    border: 1.5px solid #0284c7;
}

.table-wrapper-scroller {
    flex: 1;
    overflow: auto;
}

/* Actions Header Panel */
.table-action-row {
    display: flex;
    gap: 8px;
}

.btn-action-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.55rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #1e293b;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.1s ease;
}
.btn-action-pill:hover {
    border-color: #0284c7;
    background: #f0f9ff;
    color: #0284c7;
}
.btn-action-pill.btn-sync {
    background-color: #00E676;
    border: none;
    color: #0f172a;
    font-weight: 700;
    text-transform: uppercase;
}
.btn-action-pill.btn-sync:hover { background-color: #00c853; }

/* Premium High Density Structured Spreadsheet Layout */
.dense-matrix-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.60rem;
    text-align: left;
}
.dense-matrix-table th {
    background: #f8fafc;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.65rem;
    letter-spacing: 0.5px;
    padding: 0.4rem 0.5rem;
    border-bottom: 1px solid #e2e8f0;
    position: sticky;
    top: 0;
    user-select: none;
    cursor: pointer;
    line-height: 1;
}
.dense-matrix-table th:hover { color: #0f172a; background: #f1f5f9; }
.dense-matrix-table td {
    padding: 0.2rem 0.5rem;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
    line-height: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.dense-matrix-table tbody tr {
    cursor: pointer;
    transition: background-color 0.1s ease;
}
.dense-matrix-table tbody tr:hover { background-color: #f0f9ff; }
.dense-matrix-table tbody tr.row-selected {
    background-color: #e0f2fe !important;
    font-weight: 500;
}

/* --- RIGHT VIEWPORT CANVAS MAP --- */
.map-canvas-frame {
    flex: 1;
    position: relative;
    background: #cbd5e1;
}
#primary-workspace-map { width: 100%; height: 100%; }

/* HUD Displays */
.map-splash-hud {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1000;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    border: 1px solid #e2e8f0;
    padding: 2rem;
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 12px 40px rgba(15, 23, 42, 0.08);
    pointer-events: none;
    transition: opacity 0.2s ease;
}


/* Custom Component Elements */
.discipline-pill {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 1px 4px;
    border-radius: 3px;
    line-height: 1;
    display: inline-block;
}
.discipline-1 { background: rgba(2, 132, 199, 0.08); color: #0284c7; }
.discipline-6 { background: #fef3c7; color: #d97706; }
.discipline-2 { background: rgba(0, 230, 118, 0.1); color: #1b5e20; }
.distance-label {
    background: #0f172a !important; color: #ffffff !important; border: none !important;
    font-family: 'JetBrains Mono', monospace; font-size: 9px !important; padding: 2px 4px !important;
}
</style>

<div class="app-workspace-frame" id="workspaceMainShell">
    
    <section class="center-data-platform">
        <div class="platform-table-header">
            <div>
                <h2 style="margin:0 0 0.25rem 0; font-size:1.25rem; font-weight:800; color:#0f172a;">Track Database Matrix</h2>
                <div class="user-meta-bar">
                    <div class="profile-badge">
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="User Avatar" width="22" height="22">
                        <span style="font-size: 0.78rem; font-weight: 600; color: #334155;"><?= htmlspecialchars($user['firstname']) ?></span>
                    </div>
                    <div style="font-size: 0.72rem; color: #64748b;">
                        Sync: <span style="font-weight:600; color:#1e293b;"><?= $user['last_routes_sync'] ? date('d M', strtotime($user['last_routes_sync'])) : 'Never' ?></span>
                    </div>
                </div>
            </div>
            
            <div class="table-action-row">
                <a id="mapLink" href="map.php" class="btn-action-pill">🗺️ Full View</a>
                <button id="openFilters" class="btn-action-pill" type="button">⚙️ Filters</button>
                <button id="fetchRoutes" class="btn-action-pill btn-sync" type="button">🚀 Sync Tracks</button>
            </div>
        </div>
        
        <div class="table-wrapper-scroller">
            <table class="dense-matrix-table">
                <thead>
                    <tr>
                        <th onclick="executeMatrixSort('name')">Route Name</th>
                        <th onclick="executeMatrixSort('type')" style="width: 100px;">Discipline</th>
                        <th onclick="executeMatrixSort('distance_km')" style="width: 110px;">Distance</th>
                        <th onclick="executeMatrixSort('elevation')" style="width: 110px;">Elevation</th>
                        <th onclick="executeMatrixSort('estimated_moving_time')" style="width: 110px;">Time Est</th>
                        <th onclick="executeMatrixSort('created_date')" style="width: 120px;">Created</th>
                        <th style="width: 60px; text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody id="denseMatrixTableBody"></tbody>
            </table>
        </div>
    </section>

    <main class="map-canvas-frame">
        <div id="primary-workspace-map"></div>
        <div class="map-splash-hud" id="mapSplashHud">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🚴‍♂️</div>
            <h3 style="margin:0 0 0.25rem 0; font-weight:700; color:#0f172a;">Performance Canvas Engine</h3>
            <p style="margin:0; font-size:0.8rem; color:#64748b;">Select an active route timeline index coordinate to build spatial tracking visualizations.</p>
        </div>
    </main>

</div>

<?php include 'filter_panel.php'; ?>    

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>
<script src="routes_shared.js?v=1.0.1"></script>
    
<script>
var routes = <?= json_encode($routes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]'; ?>;
let globalWorkspaceMap = null;
let currentActivePolyline = null;
let currentDistanceMarkers = [];
let matrixSortState = { column: null, direction: 'asc' };

function formatDuration(seconds) {
    if (!seconds) return "00:00";
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function routeTypeLabel(type) {
    return { 1: 'Ride', 2: 'Run', 3: 'Walk', 6: 'Gravel' }[type] || 'Track';
}

function initWorkspaceMap() {
    globalWorkspaceMap = L.map('primary-workspace-map', { zoomControl: false }).setView([50.8503, 4.3517], 9);
    L.control.zoom({ position: 'topright' }).addTo(globalWorkspaceMap);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap Contribs &copy; CARTO'
    }).addTo(globalWorkspaceMap);
}

// Master execution renderer
async function renderTable(data) {
    renderDenseSpreadsheetMatrix(data);
}

// Center Panel Spreadsheet Data Matrix Grid Render System Loop
function renderDenseSpreadsheetMatrix(data) {
    const tableBody = document.getElementById('denseMatrixTableBody');
    if (!tableBody) return;
    tableBody.innerHTML = '';

    data.forEach(route => {
        const row = document.createElement('tr');
        row.id = `row-id-${route.route_id}`;
        
        let status = '';
        if(route.starred == 1) status += '<span style="color:#f59e0b;">★</span>';
        if(route.private == 1) status += ' 🔒';

        row.innerHTML = `
            <td style="color:#0f172a; font-weight:600;">${route.name || 'Untitled Track'}</td>
            <td><span class="discipline-pill discipline-${route.type || 1}">${routeTypeLabel(route.type)}</span></td>
            <td style="font-family:'JetBrains Mono'; font-weight:600;">${Number(route.distance_km).toFixed(1)} km</td>
            <td style="font-family:'JetBrains Mono'; color:#475569;">${Math.round(route.elevation)} m</td>
            <td style="color:#475569;">${formatDuration(route.estimated_moving_time)}</td>
            <td style="color:#64748b; font-size:0.8rem;">${route.created_date || '—'}</td>
            <td style="text-align:center;">${status || '—'}</td>
        `;
        row.onclick = () => handleTrackSelection(route, `row-id-${route.route_id}`);
        tableBody.appendChild(row);
    });
}

// Shared central targeting engine
async function handleTrackSelection(route, UIComponentElementId) {
    document.querySelectorAll('.dense-matrix-table tbody tr').forEach(r => r.classList.remove('row-selected'));

    const correspondingRow = document.getElementById(`row-id-${route.route_id}`);
    if(correspondingRow) correspondingRow.classList.add('row-selected');

    if (currentActivePolyline) globalWorkspaceMap.removeLayer(currentActivePolyline);
    currentDistanceMarkers.forEach(m => globalWorkspaceMap.removeLayer(m));
    currentDistanceMarkers = [];

    document.getElementById('mapSplashHud').style.opacity = '0';

    if (!route.summary_polyline) {
        try {
            const res = await fetch(`get_polyline.php?route_id=${route.route_id}`);
            const polyData = await res.json();
            route.summary_polyline = polyData.polyline;
        } catch (e) { console.error("Trace buffer line tracking fetch error:", e); }
    }

    if (route.summary_polyline) {
        try {
            const coords = polyline.decode(route.summary_polyline).map(c => [c[0], c[1]]);
            currentActivePolyline = L.polyline(coords, { color: '#ef4444', weight: 4.5, opacity: 0.9, lineJoin: 'round' }).addTo(globalWorkspaceMap);
            globalWorkspaceMap.fitBounds(currentActivePolyline.getBounds(), { padding: [40, 40] });
            addDistanceMarkers(coords, 10);
        } catch (err) { console.error("Leaflet drawing exception parameters:", err); }
    }
}

function addDistanceMarkers(latlngs, stepKm = 10) {
    let distance = 0;
    let nextMarker = stepKm;
    for (let i = 1; i < latlngs.length; i++) {
        distance += haversineDistance(latlngs[i - 1], latlngs[i]);
        if (distance >= nextMarker) {
            const marker = L.circleMarker(latlngs[i], { radius: 3.5, color: '#0f172a', fillColor: '#ffffff', fillOpacity: 1, weight: 2 }).addTo(globalWorkspaceMap);
            marker.bindTooltip(`${nextMarker} k`, { permanent: true, direction: 'top', className: 'distance-label', offset: [0, -4] });
            currentDistanceMarkers.push(marker);
            nextMarker += stepKm;
        }
    }
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

// Structural Matrix database table sorter block
function executeMatrixSort(column) {
    if (matrixSortState.column === column) {
        matrixSortState.direction = matrixSortState.direction === 'asc' ? 'desc' : 'asc';
    } else {
        matrixSortState.column = column;
        matrixSortState.direction = 'asc';
    }

    routes.sort((a, b) => {
        let valA = a[column];
        let valB = b[column];
        if (!isNaN(parseFloat(valA)) && isFinite(valA)) {
            return matrixSortState.direction === 'asc' ? parseFloat(valA) - parseFloat(valB) : parseFloat(valB) - parseFloat(valA);
        }
        return matrixSortState.direction === 'asc' ? String(valA).localeCompare(String(valB)) : String(valB).localeCompare(String(valA));
    });

    if (typeof applyFilters === 'function') { applyFilters(); } else { renderTable(routes); }
}

// Sync execution runtime setup
document.getElementById('fetchRoutes').addEventListener('click', async () => {
    const btn = document.getElementById('fetchRoutes');
    btn.disabled = true;
    let page = 1, keepGoing = true, totalSynced = 0;
    try {
        while (keepGoing) {
            btn.innerText = `⏳ Page ${page}...`;
            const res = await fetch(`fetch_routes.php?page=${page}`);
            const data = await res.json();
            if (!data.success) throw new Error(data.error);
            totalSynced += data.routes_in_batch;
            if (data.has_more) { page++; await new Promise(r => setTimeout(r, 1000)); } else { keepGoing = false; }
        }
        btn.innerText = "Success! Updating workspace UI...";
        setTimeout(() => location.reload(), 1000);
    } catch (e) {
        alert('Sync faulted: ' + e.message);
        btn.disabled = false;
    }
});

document.addEventListener('DOMContentLoaded', () => {
    initWorkspaceMap();
    renderTable(routes);
});
</script>
<?php include 'footer.php'; ?>
<?php exit(0); ?>
