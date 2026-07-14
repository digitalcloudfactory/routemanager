<?php
/* ===============================
----Important rule going forward----
-Use case-                 -ID to use-
DB queries                 internal_user_id ✅
Strava API calls           strava_id
Session auth check         internal_user_id
================================ */

require_once 'config.php'; // 🟩 Everything loads instantly


// Access control layer: kick them out to index if they aren't authenticated
if (!isset($_SESSION['internal_user_id'])) {
    header("Location: index.php");
    exit;
}
$internalUserId = $_SESSION['internal_user_id'];

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
    ORDER BY created_at DESC
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

<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<!-- Bootstrap 5 JS Bundle (Put this right before </head> or near footer.php) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

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
    padding: 0.6rem 1rem; /* Reduced from 1.5rem */
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.user-meta-bar {
    display: flex;
    align-items: center;
    gap: 8px; /* Reduced from 12px */
}

.profile-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px; /* Reduced from 8px */
    background: #f8fafc;
    padding: 0.15rem 0.5rem 0.15rem 0.15rem; /* Reduced padding */
    border-radius: 50px;
    border: 1px solid #e2e8f0;
}
.profile-badge img {
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #0284c7; /* Reduced from 1.5px */
}

.table-wrapper-scroller {
    flex: 1;
    overflow: auto;
}

/* Actions Header Panel */
.table-action-row {
    display: flex;
    gap: 4px;
    align-items: center; /* Ensures all items center vertically relative to each other */
}

.btn-action-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 3px;
    font-size: 0.68rem;
    font-weight: 600;
    line-height: 1;
    height: 24px;                  /* Fixed uniform height across links and buttons */
    padding: 0 0.45rem;             /* Horizontal padding (vertical handled by height) */
    box-sizing: border-box;     /* Keeps border included in total height calculation */
    border-radius: 4px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    margin: 0;                      /* Clears default button browser margins */
    vertical-align: middle;
    transition: all 0.15s ease;
}

.btn-action-pill:hover {
    border-color: #0284c7;
    background: #f0f9ff;
    color: #0284c7;
}

.btn-action-pill.btn-sync {
    background-color: #00E676;
    border: 1px solid #00E676;     /* Matching 1px border so height stays identical */
    color: #0f172a;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.65rem;
}

.btn-action-pill.btn-sync:hover { 
    background-color: #00c853; 
    border-color: #00c853;
}

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

/* Focus / Fullscreen Mode Styles */
.map-canvas-frame.map-focus-mode {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 99999 !important; /* Forces it over table, headers, and sidebars */
    margin: 0 !important;
    border-radius: 0 !important;
    background: #ffffff;
}

/* Fix Leaflet map sizing inside focus container */
.map-focus-mode #primary-workspace-map {
    width: 100vw !important;
    height: 100vh !important;
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

/* Fix Leaflet Zoom Control button centering misalignment */
.leaflet-control-zoom a {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    line-height: 1 !important;
    padding: 0 !important;
    text-decoration: none !important;
    font-size: 18px !important;
}

.leaflet-marker-icon,
.leaflet-marker-shadow {
    background: none !important;
    border: none !important;
    box-shadow: none !important;
}

/* Sleek Action Button Styling */
#btnFetchPois {
  transition: all 0.2s ease-in-out;
  border-width: 1.5px;
}

#btnFetchPois:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(2, 132, 199, 0.15) !important;
}

#btnFetchPois:active {
  transform: translateY(0);
}

/* Spinner animation state */
.spin-icon {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* ==========================================================================
   FULLSCREEN / FOCUS MAP MODE OVERLAY
   ========================================================================== */
#mapContainer.map-focus-mode {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 9999 !important;
    margin: 0 !important;
    border-radius: 0 !important;
    box-shadow: none !important;
}

/* Ensure controls overlay sits neatly inside focus mode */
#mapControlOverlay {
    transition: opacity 0.2s ease-in-out;
}


/* ==========================================================================
   ENHANCED DISTANCE & MILESTONE MARKERS
   ========================================================================== */
.km-marker-container {
    background: transparent !important;
    border: none !important;
}

.km-badge {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(4px);
    color: #0f172a;
    font-family: 'Inter', -apple-system, sans-serif;
    font-weight: 700;
    font-size: 11px;
    height: 26px;
    min-width: 26px;
    padding: 0 6px;
    line-height: 22px;
    border-radius: 13px; /* Smooth pill shape */
    border: 2px solid #0284c7; /* Vibrant Blue Accent */
    box-shadow: 0 3px 8px rgba(0,0,0,0.2), 0 0 0 1px rgba(255,255,255,0.5);
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    cursor: pointer;
    user-select: none;
}

.km-badge:hover {
    transform: scale(1.18);
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.4);
    z-index: 1000 !important;
}

/* Start Milestone (Green Accent) */
.km-badge-start {
    border-color: #10b981 !important;
    color: #065f46;
    background: #ecfdf5;
}

/* Finish Milestone (Dark / Red Accent) */
.km-badge-finish {
    border-color: #ef4444 !important;
    color: #991b1b;
    background: #fef2f2;
}

</style>

<div class="app-workspace-frame" id="workspaceMainShell">
    
    <section class="center-data-platform">
        <div class="platform-table-header">
            <div>
                <div class="user-meta-bar">
                    <div class="profile-badge">
                        <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="User Avatar" width="16" height="16">
                        <span style="font-size: 0.7rem; font-weight: 600; color: #334155;"><?= htmlspecialchars($user['firstname']) ?></span>
                    </div>
                    <div style="font-size: 0.68rem; color: #64748b;">
                        Sync: <span style="font-weight:600; color:#1e293b;"><?= !empty($user['last_routes_sync']) ? htmlspecialchars($user['last_routes_sync']) : 'Never' ?></span>
                    </div>
                </div>
            </div>
            
            <div class="table-action-row">
                <a id="mapLink" href="map.php" class="btn-action-pill">🗺️ Full View</a>
                <a href="duplicate_finder.php" class="btn-action-pill">👥 Find Duplicates</a>
                <button id="openFilters" class="btn-action-pill" type="button">⚙️ Filters & Configs</button>
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
                        <th onclick="executeMatrixSort('starred')" style="width: 45px; text-align:center;">Star</th>
                        <th onclick="executeMatrixSort('private')" style="width: 50px; text-align:center;">Type</th>
                    </tr>
                </thead>
                <tbody id="denseMatrixTableBody"></tbody>
            </table>
        </div>
    </section>

<main class="map-canvas-frame position-relative" id="mapCanvasFrame">
    <div id="primary-workspace-map"></div>

    <!-- HUD Overlay (Fades out when route is clicked) -->
    <div class="map-splash-hud" id="mapSplashHud">
        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🚴‍♂️</div>
        <h3 style="margin:0 0 0.25rem 0; font-weight:700; color:#0f172a;">Performance Canvas Engine</h3>
        <p style="margin:0; font-size:0.8rem; color:#64748b;">Select an active route timeline index coordinate to build spatial tracking visualizations.</p>
    </div>

    <!-- Floating Map Action Overlay (Hidden until a route is selected) -->
    <div id="mapControlOverlay" class="position-absolute top-0 end-0 m-3 d-flex gap-2" style="z-index: 1000; display: none !important;">
        
        <!-- 1. Find POIs Button -->
        <button 
            type="button" 
            id="btnFetchPois" 
            class="btn btn-sm btn-white bg-white shadow-sm rounded-pill d-inline-flex align-items-center gap-2 px-3 border"
            onclick="refreshShops()"
            title="Find shops and water stops along route"
        >
            <i id="poiBtnIcon" class="bi bi-shop fs-6 text-primary"></i>
            <span class="fw-semibold text-dark" style="font-size: 0.8rem;">Find Stops</span>
            <span id="shopCount" class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 ms-1">0</span>
        </button>

        <!-- 2. Fullscreen / Focus Mode Toggle Button -->
        <button 
            type="button" 
            id="btnToggleMapFocus" 
            class="btn btn-sm btn-white bg-white shadow-sm rounded-pill d-inline-flex align-items-center gap-2 px-3 border"
            onclick="toggleMapFocusMode()"
            title="Expand map to full screen"
        >
            <i id="mapFocusIcon" class="bi bi-arrows-fullscreen text-secondary"></i>
            <span id="mapFocusText" class="fw-semibold text-dark" style="font-size: 0.8rem;">Focus</span>
        </button>

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

        const starIcon = (route.starred == 1) ? '<span style="color:#f59e0b;">★</span>' : '<span style="color:#cbd5e1;">☆</span>';
        const privacyIcon = (route.private == 1) ? '<span style="color:#64748b;" title="Private">🔒</span>' : '<span style="color:#cbd5e1;" title="Public">🌐</span>';
        
        row.innerHTML = `
            <td style="color:#0f172a; font-weight:600;">${route.name || 'Untitled Track'}</td>
            <td><span class="discipline-pill discipline-${route.type || 1}">${routeTypeLabel(route.type)}</span></td>
            <td style="font-family:'JetBrains Mono'; font-weight:600;">${Number(route.distance_km).toFixed(1)} km</td>
            <td style="font-family:'JetBrains Mono'; color:#475569;">${Math.round(route.elevation)} m</td>
            <td style="color:#475569;">${formatDuration(route.estimated_moving_time)}</td>
            <td style="font-family:'JetBrains Mono'; color:#64748b;">${route.created_date || '—'}</td>
            <td style="text-align:center; font-size: 0.85rem;">${starIcon}</td>
            <td style="text-align:center; font-size: 0.8rem;">${privacyIcon}</td>
        `;
        row.onclick = () => handleTrackSelection(route, `row-id-${route.route_id}`);
        tableBody.appendChild(row);
    });
}

// Shared central targeting engine
// Shared central targeting engine
async function handleTrackSelection(route, UIComponentElementId) {
    // 1. Highlight selected row in spreadsheet
    document.querySelectorAll('.dense-matrix-table tbody tr').forEach(r => r.classList.remove('row-selected'));
    const correspondingRow = document.getElementById(`row-id-${route.route_id}`);
    if (correspondingRow) correspondingRow.classList.add('row-selected');

    // 2. CLEAR ALL PREVIOUS MAP LAYERS BEFORE DRAWING NEW ROUTE
    if (currentActivePolyline) {
        globalWorkspaceMap.removeLayer(currentActivePolyline);
        currentActivePolyline = null;
    }

    // Clear 10km step markers
    currentDistanceMarkers.forEach(m => globalWorkspaceMap.removeLayer(m));
    currentDistanceMarkers = [];

    // Clear previous shop / water POI markers
    if (poiMarkersGroup) {
        poiMarkersGroup.clearLayers();
    }
    
    // Reset shop counter badge
    updateShopCountBadges(0);

    // Fade out splash overlay HUD
    const splashHud = document.getElementById('mapSplashHud');
    if (splashHud) splashHud.style.opacity = '0';


    // Reveal overlay buttons when route is selected
    const overlay = document.getElementById('mapControlOverlay');
    if (overlay) {
        overlay.style.setProperty('display', 'flex', 'important');
    }

    // 3. Fetch polyline if not already loaded in memory
    if (!route.summary_polyline) {
        try {
            const res = await fetch(`get_polyline.php?route_id=${route.route_id}`);
            const polyData = await res.json();
            route.summary_polyline = polyData.polyline;
        } catch (e) { 
            console.error("Trace buffer line tracking fetch error:", e); 
            return;
        }
    }

    // 4. Draw new polyline
    if (route.summary_polyline) {
        try {
            const coords = polyline.decode(route.summary_polyline).map(c => [c[0], c[1]]);
            
            // Store coordinates for Overpass POI searches
            currentRouteCoords = coords;

            // Draw new polyline & assign to tracking variable
            currentActivePolyline = L.polyline(coords, { 
                color: '#ef4444', 
                weight: 4.5, 
                opacity: 0.9, 
                lineJoin: 'round' 
            }).addTo(globalWorkspaceMap);

            // Fit map to new route bounds
            globalWorkspaceMap.fitBounds(currentActivePolyline.getBounds(), { padding: [40, 40] });
            
            // Place 10km step markers
            addDistanceMarkers(coords, 10);
        } catch (err) { 
            console.error("Leaflet drawing exception parameters:", err); 
        }
    }
}

/**
 * Places distance milestone badges (e.g., Start, 10km, 20km... Finish) along the route polyline.
 * @param {Array} coords - Array of [lat, lng] coordinates from polyline decoding.
 * @param {number} intervalKm - Interval in kilometers (default 10km).
 */
function addDistanceMarkers(coords, intervalKm = 10) {
    if (!coords || coords.length < 2) return;

    // Haversine distance calculator between two [lat, lng] points in meters
    function getDistanceMeters(p1, p2) {
        const R = 6371000; // Earth radius in meters
        const dLat = (p2[0] - p1[0]) * Math.PI / 180;
        const dLon = (p2[1] - p1[1]) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(p1[0] * Math.PI / 180) * Math.cos(p2[0] * Math.PI / 180) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    let accumulatedDistance = 0;
    let nextMilestone = intervalKm * 1000; // convert to meters

    // 1. Place Start Pin (0 km)
    const startIcon = L.divIcon({
        className: 'km-marker-container',
        html: `<div class="km-badge km-badge-start" title="Start Point">0 km</div>`,
        iconSize: [36, 26],
        iconAnchor: [18, 13]
    });
    const startMarker = L.marker(coords[0], { icon: startIcon }).addTo(globalWorkspaceMap);
    startMarker.bindTooltip("🚩 <strong>Start</strong> (0 km)", { direction: 'top', offset: [0, -10] });
    currentDistanceMarkers.push(startMarker);

    // 2. Loop through route and place intermediate KM markers
    for (let i = 0; i < coords.length - 1; i++) {
        const p1 = coords[i];
        const p2 = coords[i + 1];
        const segmentDist = getDistanceMeters(p1, p2);

        while (accumulatedDistance + segmentDist >= nextMilestone) {
            // Interpolate position along line segment
            const ratio = (nextMilestone - accumulatedDistance) / segmentDist;
            const lat = p1[0] + (p2[0] - p1[0]) * ratio;
            const lng = p1[1] + (p2[1] - p1[1]) * ratio;

            const kmValue = Math.round(nextMilestone / 1000);

            const kmIcon = L.divIcon({
                className: 'km-marker-container',
                html: `<div class="km-badge">${kmValue}</div>`,
                iconSize: [28, 26],
                iconAnchor: [14, 13]
            });

            const marker = L.marker([lat, lng], { icon: kmIcon }).addTo(globalWorkspaceMap);
            marker.bindTooltip(`📍 <strong>${kmValue} km</strong> point`, { direction: 'top', offset: [0, -10] });
            currentDistanceMarkers.push(marker);

            nextMilestone += intervalKm * 1000;
        }

        accumulatedDistance += segmentDist;
    }

    // 3. Place Finish Pin
    const totalKm = (accumulatedDistance / 1000).toFixed(1);
    const finishIcon = L.divIcon({
        className: 'km-marker-container',
        html: `<div class="km-badge km-badge-finish" title="Finish Line">${totalKm}k</div>`,
        iconSize: [42, 26],
        iconAnchor: [21, 13]
    });
    const finishMarker = L.marker(coords[coords.length - 1], { icon: finishIcon }).addTo(globalWorkspaceMap);
    finishMarker.bindTooltip(`🏁 <strong>Finish Line</strong> (${totalKm} km total)`, { direction: 'top', offset: [0, -10] });
    currentDistanceMarkers.push(finishMarker);
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

/**
 * Haversine Formula: Returns distance in kilometers between two lat/lng coordinates
 */
function getHaversineDistanceKm(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in kilometers
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon / 2) * Math.sin(dLon / 2);
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
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

// Master Application Synchronization Bootstrapper inside routes.php
document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialize the Leaflet workspace map layout container
    initWorkspaceMap();

    // 2. CRITICAL: Force routes_shared to re-read URL query bars 
    // now that the map canvas and the embedded database rows are entirely ready
    if (typeof loadFiltersFromURL === 'function') {
        loadFiltersFromURL();
    } else if (typeof applyFilters === 'function') {
        applyFilters();
    } else {
        renderTable(routes);
    }

    // --- VIEW LINK INTERCEPTOR ---
    const viewToggleLink = document.getElementById('mapLink');
    if (viewToggleLink) {
        viewToggleLink.addEventListener('click', (e) => {
            e.preventDefault();
            const targetUrl = new URL(viewToggleLink.getAttribute('href'), window.location.origin);
            
            // Forwards exactly what routes_shared.js put in the browser URL bar
            window.location.href = `${targetUrl.pathname}${window.location.search}`;
        });
    }
});


/* ===============================
    STOPS PLANNER / OVERPASS POI ENGINE
================================ */
let currentRouteCoords = []; // Holds current [[lat, lng], ...]
let poiMarkersGroup = L.layerGroup(); // Layer group to easily add/remove POI markers

/**
 * Updates all badge count elements on the page simultaneously
 */
function updateShopCountBadges(value) {
    document.querySelectorAll("#shopCount").forEach(elem => {
        elem.innerText = value;
    });
}

/**
 * Executes the Overpass API query to fetch supermarkets, convenience stores, or water points.
 */
async function fetchSupermarkets(coords) {
    if (!coords || coords.length === 0) {
        alert("Please select a route first.");
        return;
    }

    const sundayOnly = document.getElementById("SundayBox")?.checked || false;
    const waterOnly = document.getElementById("drinkFountains")?.checked || false;
    const searchRadius = parseInt(document.getElementById("radiusSelect")?.value || "500", 10);

    // Filter points to prevent sending overly dense requests to Overpass API
    const step = Math.max(1, Math.floor(coords.length / 50));
    const sampledCoords = coords.filter((_, idx) => idx % step === 0);

    // Get UI Elements
    const btn = document.getElementById("btnFetchPois");
    const icon = document.getElementById("poiBtnIcon");

    // 🔄 UI: Set Loading State
    if (btn) btn.disabled = true;
    if (icon) {
        icon.className = "bi bi-arrow-repeat spin-icon fs-6"; // Switch icon to spinner
    }
    updateShopCountBadges("...");

    // Clear existing markers from map
    if (globalWorkspaceMap) {
        poiMarkersGroup.clearLayers();
        if (!globalWorkspaceMap.hasLayer(poiMarkersGroup)) {
            poiMarkersGroup.addTo(globalWorkspaceMap);
        }
    }

    // Build bounding queries for sampled points
    let queryParts = [];
    sampledCoords.forEach(c => {
        const lat = c[0];
        const lon = c[1];
        
        if (waterOnly) {
            queryParts.push(`node["amenity"="drinking_water"](around:${searchRadius},${lat},${lon});`);
        } else if (sundayOnly) {
            queryParts.push(`node["shop"="supermarket"]["brand"~"Spar|Delhaize|Carrefour", i](around:${searchRadius},${lat},${lon});`);
            queryParts.push(`way["shop"="supermarket"]["brand"~"Spar|Delhaize|Carrefour", i](around:${searchRadius},${lat},${lon});`);
        } else {
            queryParts.push(`node["shop"~"supermarket|convenience"](around:${searchRadius},${lat},${lon});`);
            queryParts.push(`way["shop"~"supermarket|convenience"](around:${searchRadius},${lat},${lon});`);
        }
    });

    const overpassQuery = `[out:json][timeout:25];(${queryParts.join("")});out center;`;

    try {
        // Send as URLSearchParams (guarantees $_POST['query'] is populated in PHP)
        const response = await fetch('/overpass.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({ query: overpassQuery })
        });

        if (!response.ok) throw new Error(`HTTP Error ${response.status}: Failed to fetch POIs`);
        
        const data = await response.json();

        let foundCount = 0;
        const seenIds = new Set();

        data.elements.forEach(item => {
            if (seenIds.has(item.id)) return;
            seenIds.add(item.id);

            const lat = item.lat || (item.center && item.center.lat);
            const lon = item.lon || (item.center && item.center.lon);
            const name = item.tags?.name || (waterOnly ? "Water Tap Point" : "Local Shop");

            if (lat && lon) {
                foundCount++;
                
                // Standard Leaflet Pin
                const poiMarker = L.marker([lat, lon]);

                poiMarker.bindPopup(`
                    <div style="font-family: Inter, sans-serif; padding: 4px;">
                        <strong style="font-size: 0.85rem; color: #0f172a;">${name}</strong><br>
                        <span style="font-size: 0.75rem; color: #64748b;">${waterOnly ? 'Drinking Water' : (item.tags?.shop || 'Store')}</span>
                    </div>
                `);

                poiMarkersGroup.addLayer(poiMarker);
            }
        });

        updateShopCountBadges(foundCount);

    } catch (err) {
        console.error("Error fetching POIs from Overpass:", err);
        updateShopCountBadges("Err");
    } finally {
        // 🔄 UI: Restore Normal State
        if (btn) btn.disabled = false;
        if (icon) {
            icon.className = waterOnly ? "bi bi-droplet-fill fs-6" : "bi bi-shop fs-6";
        }
    }
}

/**
 * Triggered by the "Find POIs Along Route" button in the offcanvas sidebar.
 */
function refreshShops() {
    if (currentRouteCoords && currentRouteCoords.length > 0) {
        fetchSupermarkets(currentRouteCoords);
    } else {
        alert("Please select a route from the table first.");
    }
}


function toggleMapFocusMode(forceClose = false) {
    const mapFrame = document.getElementById('mapCanvasFrame');
    const icon = document.getElementById('mapFocusIcon');
    const text = document.getElementById('mapFocusText');

    if (!mapFrame) {
        console.error("mapCanvasFrame element not found!");
        return;
    }

    const isCurrentlyFocused = mapFrame.classList.contains('map-focus-mode');

    if (isCurrentlyFocused || forceClose) {
        // Exit Focus Mode
        mapFrame.classList.remove('map-focus-mode');
        if (icon) icon.className = 'bi bi-arrows-fullscreen text-secondary';
        if (text) text.textContent = 'Focus';
    } else {
        // Enter Focus Mode
        mapFrame.classList.add('map-focus-mode');
        if (icon) icon.className = 'bi bi-x-lg text-danger';
        if (text) text.textContent = 'Close';
    }

    // Crucial: Force Leaflet map redraw to recalculate new width/height instantly
    setTimeout(() => {
        if (typeof globalWorkspaceMap !== 'undefined' && globalWorkspaceMap) {
            globalWorkspaceMap.invalidateSize();
            if (typeof currentActivePolyline !== 'undefined' && currentActivePolyline) {
                globalWorkspaceMap.fitBounds(currentActivePolyline.getBounds(), { padding: [40, 40] });
            }
        }
    }, 150);
}

// Press 'Esc' key to close Focus Mode automatically
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        toggleMapFocusMode(true);
    }
});
</script>
<?php include 'footer.php'; ?>
<?php exit(0); ?>
