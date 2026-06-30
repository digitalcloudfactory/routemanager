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
    overflow: hidden; /* App view shell standard */
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

/* --- TELEMETRY SIDEBAR HUB --- */
.telemetry-sidebar {
    width: 420px;
    min-width: 420px;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    z-index: 20;
    box-shadow: 4px 0 32px rgba(15, 23, 42, 0.03);
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Sidebar Hiding mechanics for full spreadsheet display states */
.app-workspace-frame.fullscreen-table-mode .telemetry-sidebar {
    margin-left: -420px;
    opacity: 0;
    pointer-events: none;
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.brand-wrapper h1 {
    font-size: 1.4rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.75px;
    margin: 0 0 0.5rem 0;
}
.brand-wrapper h1 span { color: #0284c7; }

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

/* System Workspace Mode Controller Switch */
.view-mode-selector-bar {
    display: flex;
    background: #f1f5f9;
    padding: 4px;
    border-radius: 8px;
    margin-bottom: 1rem;
    border: 1px solid #e2e8f0;
}
.mode-toggle-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.5rem;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    transition: all 0.15s ease;
}
.mode-toggle-btn.active-mode {
    background: #ffffff;
    color: #0f172a;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.05);
}

.action-grid-layout {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    padding: 0 1.5rem 1.25rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
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
    grid-column: span 2;
    background-color: #00E676;
    border: none;
    color: #0f172a;
    font-weight: 700;
    text-transform: uppercase;
}
.btn-action-pill.btn-sync:hover { background-color: #00c853; }

/* Dynamic Layout Containers */
.track-feed-container {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
    background: #f8fafc;
}

/* Hyper-Styled Cards view option layout */
.track-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    cursor: pointer;
    transition: all 0.15s ease;
}
.track-card:hover {
    border-color: #0284c7;
    transform: translateX(2px);
}
.track-card.active-track {
    border-color: #0284c7;
    background: #f0f9ff;
    box-shadow: 0 0 0 1px #0284c7;
}
.track-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 0.6rem;
}
.track-card-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    line-height: 1.3;
}
.telemetry-row {
    display: flex;
    gap: 8px;
    background: #f8fafc;
    border-radius: 6px;
    padding: 0.5rem;
    border: 1px solid #e2e8f0;
}
.track-card.active-track .telemetry-row { background: #ffffff; }
.telemetry-item { flex: 1; text-align: center; }
.telemetry-label { font-size: 0.6rem; text-transform: uppercase; font-weight: 600; color: #64748b; }
.telemetry-value { font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; font-weight: 600; color: #0f172a; }

/* --- CENTER DATA PLATFORM PANEL (THE PURE TABLE VIEW) --- */
.center-data-platform {
    display: none; /* Controlled dynamically by JS Workspace switcher */
    flex: 1.2;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    flex-direction: column;
    z-index: 15;
    box-shadow: 6px 0 24px rgba(15, 23, 42, 0.02);
}
.app-workspace-frame.fullscreen-table-mode .center-data-platform {
    display: flex;
}

.platform-table-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.table-wrapper-scroller {
    flex: 1;
    overflow-auto: auto;
}

/* Premium High Density Structured Spreadsheet Layout */
.dense-matrix-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
    text-align: left;
}
.dense-matrix-table th {
    background: #f8fafc;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.72rem;
    letter-spacing: 0.5px;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    position: sticky;
    top: 0;
    user-select: none;
    cursor: pointer;
}
.dense-matrix-table th:hover { color: #0f172a; background: #f1f5f9; }
.dense-matrix-table td {
    padding: 0.65rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
    vertical-align: middle;
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
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 2px 6px;
    border-radius: 4px;
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
    
    <aside class="telemetry-sidebar">
        <div class="sidebar-header">
            <div class="brand-wrapper">
                <h1>Strava <span>Workspace</span></h1>
            </div>
            
            <div class="view-mode-selector-bar">
                <button class="mode-toggle-btn active-mode" onclick="switchWorkspaceLayout('cards')">📁 Feed Cards</button>
                <button class="mode-toggle-btn" onclick="switchWorkspaceLayout('table')">📊 Data Matrix Table</button>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="profile-badge">
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="User Avatar" width="22" height="22">
                    <span style="font-size: 0.78rem; font-weight: 600; color: #334155;"><?= htmlspecialchars($user['firstname']) ?></span>
                </div>
                <div style="font-size: 0.72rem; color: #64748b;">
                    Sync: <span style="font-weight:600; color:#1e293b;"><?= $user['last_routes_sync'] ? date('d M', strtotime($user['last_routes_sync'])) : 'Never' ?></span>
                </div>
            </div>
        </div>

        <div class="action-grid-layout">
            <a id="mapLink" href="map.php" class="btn-action-pill">🗺️ Full View</a>
            <button id="openFilters" class="btn-action-pill" type="button">⚙️ Filters</button>
            <button id="fetchRoutes" class="btn-action-pill btn-sync" type="button">🚀 Sync Strava Tracks</button>
        </div>

        <div class="track-feed-container" id="trackFeedContainer"></div>
    </aside>

    <section class="center-data-platform">
        <div class="platform-table-header">
            <div>
                <h2 style="margin:0; font-size:1.25rem; font-weight:800; color:#0f172a;">Track Database Matrix</h2>
                <p style="margin:0; font-size:0.78rem; color:#64748b;">Click any row index line track segment parameters to project maps instantly.</p>
            </div>
            <button class="btn-action-pill" onclick="switchWorkspaceLayout('cards')" style="border-color:#0284c7; color:#0284c7; background:#f0f9ff;">✕ Close Table View</button>
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
let workspaceViewMode = 'cards'; 
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

// Global Workspace Master Route Layout Swapper Controller
function switchWorkspaceLayout(targetMode) {
    workspaceViewMode = targetMode;
    const shell = document.getElementById('workspaceMainShell');
    const toggles = document.querySelectorAll('.mode-toggle-btn');
    
    toggles.forEach(b => b.classList.remove('active-mode'));

    if (targetMode === 'table') {
        shell.classList.add('fullscreen-table-mode');
        toggles[1].classList.add('active-mode');
    } else {
        shell.classList.remove('fullscreen-table-mode');
        toggles[0].classList.add('active-mode');
    }
    
    // Auto recalculate map view borders safely during layout reflows
    setTimeout(() => { if(globalWorkspaceMap) globalWorkspaceMap.invalidateSize(); }, 310);
}

// Combined dual UI renderer
async function renderTable(data) {
    renderCardFeedLayout(data);
    renderDenseSpreadsheetMatrix(data);
}

// 1. Sidebar Cards Render System Loop
function renderCardFeedLayout(data) {
    const feedContainer = document.getElementById('trackFeedContainer');
    if (!feedContainer) return;
    feedContainer.innerHTML = '';

    data.forEach(route => {
        const card = document.createElement('div');
        card.className = 'track-card';
        card.id = `card-id-${route.route_id}`;
        
        let status = (route.starred == 1 ? '★' : '') + (route.private == 1 ? ' 🔒' : '');

        card.innerHTML = `
            <div class="track-card-header">
                <h4 class="track-card-title">${route.name || 'Untitled Track'}</h4>
                <span class="discipline-pill discipline-${route.type || 1}">${routeTypeLabel(route.type)}</span>
            </div>
            <div class="telemetry-row">
                <div class="telemetry-item"><div class="telemetry-label">Dist</div><div class="telemetry-value">${Number(route.distance_km).toFixed(1)}<span>km</span></div></div>
                <div class="telemetry-item"><div class="telemetry-label">Elev</div><div class="telemetry-value">${Math.round(route.elevation)}<span>m</span></div></div>
                <div class="telemetry-item"><div class="telemetry-label">Est</div><div class="telemetry-value" style="font-size:0.75rem;">${formatDuration(route.estimated_moving_time)}</div></div>
            </div>
        `;
        card.onclick = () => handleTrackSelection(route, `card-id-${route.route_id}`);
        feedContainer.appendChild(card);
    });
}

// 2. Center Panel Spreadsheet Data Matrix Grid Render System Loop
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
    // Clear selections safely across elements
    document.querySelectorAll('.track-card').forEach(c => c.classList.remove('active-track'));
    document.querySelectorAll('.dense-matrix-table tbody tr').forEach(r => r.classList.remove('row-selected'));

    const correspondingCard = document.getElementById(`card-id-${route.route_id}`);
    const correspondingRow = document.getElementById(`row-id-${route.route_id}`);
    
    if(correspondingCard) correspondingCard.classList.add('active-track');
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
