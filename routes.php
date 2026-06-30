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

<!-- External Resource Engine Layer -->
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
    overflow: hidden; /* Prevents whole-page scrolling for true app workspace design */
    -webkit-font-smoothing: antialiased;
}

/* Master App Layout Canvas Container */
.app-workspace-frame {
    display: flex;
    width: 100vw;
    height: 100vh;
    box-sizing: border-box;
}

/* --- LEFT TELEMETRY HUB PANEL --- */
.telemetry-sidebar {
    width: 440px;
    min-width: 440px;
    background: #ffffff;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    z-index: 10;
    box-shadow: 4px 0 32px rgba(15, 23, 42, 0.04);
}

.sidebar-header {
    padding: 1.75rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
}

.brand-wrapper h1 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0f172a;
    letter-spacing: -0.75px;
    margin: 0 0 0.5rem 0;
}
.brand-wrapper h1 span {
    color: #0284c7;
    position: relative;
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

/* Scrollable Track Feed Container */
.track-feed-container {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem 1rem;
    background: #f8fafc;
}

/* Refined Athletic Control Buttons */
.action-grid-layout {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    background: #ffffff;
}

.btn-action-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.6rem 0.85rem;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #1e293b;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s cubic-bezier(0.16, 1, 0.3, 1);
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
    letter-spacing: 0.5px;
    box-shadow: 0 4px 12px rgba(0, 230, 118, 0.2);
}
.btn-action-pill.btn-sync:hover {
    background-color: #00c853;
    transform: translateY(-1px);
    color: #0f172a;
}

/* Hyper-styled Performance Metric Cards */
.track-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    cursor: pointer;
    position: relative;
    transition: all 0.2s ease;
}
.track-card:hover {
    border-color: #0284c7;
    transform: translateX(2px);
    box-shadow: 0 4px 20px rgba(2, 132, 199, 0.05);
}
.track-card.active-track {
    border-color: #0284c7;
    background: #f0f9ff;
    box-shadow: 0 0 0 1px #0284c7, 0 8px 24px rgba(2, 132, 199, 0.06);
}

.track-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 0.75rem;
}
.track-card-title {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.35;
    margin: 0;
}

.telemetry-row {
    display: flex;
    gap: 12px;
    background: #f8fafc;
    border-radius: 8px;
    padding: 0.6rem;
    border: 1px solid #e2e8f0;
}
.track-card.active-track .telemetry-row {
    background: #ffffff;
    border-color: rgba(2, 132, 199, 0.15);
}

.telemetry-item {
    flex: 1;
    text-align: center;
}
.telemetry-label {
    font-size: 0.65rem;
    text-transform: uppercase;
    font-weight: 600;
    color: #64748b;
    letter-spacing: 0.5px;
    margin-bottom: 1px;
}
.telemetry-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.88rem;
    font-weight: 600;
    color: #0f172a;
}
.telemetry-value span {
    font-size: 0.7rem;
    color: #64748b;
    margin-left: 1px;
    font-family: 'Inter', sans-serif;
}

/* Expanded Metadata Field Wrapper inside Sidebar Card */
.track-extended-panel {
    margin-top: 0.75rem;
    padding-top: 0.75rem;
    border-top: 1px dashed #e2e8f0;
    display: none;
}
.track-card.active-track .track-extended-panel {
    display: block;
}

.tag-input-style {
    width: 100%;
    padding: 0.45rem 0.65rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    font-size: 0.8rem;
    color: #1e293b;
    box-sizing: border-box;
}
.tag-input-style:focus {
    outline: none;
    border-color: #0284c7;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.12);
}

/* Custom Sport Badges */
.discipline-pill {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 2px 6px;
    border-radius: 4px;
    background: #e2e8f0;
    color: #475569;
    white-space: nowrap;
}
.discipline-1 { background-color: rgba(2, 132, 199, 0.08); color: #0284c7; }
.discipline-6 { background-color: #fef3c7; color: #d97706; }
.discipline-2 { background-color: rgba(0, 230, 118, 0.1); color: #1b5e20; }

/* --- RIGHT VIEWPORT CANVAS MAP --- */
.map-canvas-frame {
    flex: 1;
    position: relative;
    background: #cbd5e1;
}
#primary-workspace-map {
    width: 100%;
    height: 100%;
}

/* Interactive Map Splash HUD Overlay */
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
    transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Custom Leaflet Map Tooltip adjustments */
.distance-label {
    background: #0f172a !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 4px !important;
    font-weight: 700 !important;
    font-family: 'JetBrains Mono', monospace !important;
    font-size: 10px !important;
    padding: 2px 5px !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}
</style>

<div class="app-workspace-frame">
    
    <!-- LEFT PANEL: Dynamic List & Feed Engine -->
    <aside class="telemetry-sidebar">
        
        <div class="sidebar-header">
            <div class="brand-wrapper">
                <h1>Strava <span>Workspace</span></h1>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="profile-badge">
                    <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="User Avatar" width="24" height="24">
                    <span style="font-size: 0.8rem; font-weight: 600; color: #334155;">
                        <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                    </span>
                </div>
                <div style="font-size: 0.75rem; color: #64748b; text-align: right;">
                    Sync: <span style="font-weight:600; color:#1e293b;"><?= $user['last_routes_sync'] ? date('d M H:i', strtotime($user['last_routes_sync'])) : 'Never' ?></span>
                </div>
            </div>
        </div>

        <div class="action-grid-layout">
            <a id="mapLink" href="map.php" class="btn-action-pill">🗺️ Full Map</a>
            <a id="dubLink" href="duplicate_finder.php" class="btn-action-pill">Duplicate Tool</a>
            <button id="openFilters" class="btn-action-pill" type="button">⚙️ Track Filters</button>
            <button id="fetchRoutes" class="btn-action-pill btn-sync" type="button">🚀 Sync Strava Tracks</button>
        </div>

        <!-- Scrollable Track Units Feed -->
        <div class="track-feed-container" id="trackFeedContainer">
            <!-- Programmatic feed updates render inside this canvas zone -->
        </div>

    </aside>

    <!-- RIGHT PANEL: Full Horizon Map Engine Frame -->
    <main class="map-canvas-frame">
        <div id="primary-workspace-map"></div>
        
        <div class="map-splash-hud" id="mapSplashHud">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🚴‍♂️</div>
            <h3 style="margin:0 0 0.25rem 0; font-weight:700; color:#0f172a;">Performance Analytics Frame</h3>
            <p style="margin:0; font-size:0.85rem; color:#64748b;">Select an active telemetry track route profile trace to render map spatial layers.</p>
        </div>
    </main>

</div>

<?php include 'filter_panel.php'; ?>   

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>
<script src="routes_shared.js?v=1.0.1"></script>
    
<script>
// Master Reactive Data Variables Storage
var routes = <?= json_encode($routes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '[]'; ?>;
let globalWorkspaceMap = null;
let currentActivePolyline = null;
let currentDistanceMarkers = [];

function formatDuration(seconds) {
    if (!seconds) return "00:00";
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function routeTypeLabel(type) {
    return { 1: 'Ride', 2: 'Run', 3: 'Walk', 6: 'Gravel' }[type] || 'Track';
}

// Initialize Leaflet Workspace Engine Layer
function initWorkspaceMap() {
    globalWorkspaceMap = L.map('primary-workspace-map', {
        zoomControl: false 
    }).setView([50.8503, 4.3517], 9); // Defaults spatial center metrics

    L.control.zoom({ position: 'topright' }).addTo(globalWorkspaceMap);

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap Contributors &copy; CARTO'
    }).addTo(globalWorkspaceMap);
}

// Master Render System replacing outdated flat text table grids
async function renderTable(data) {
    const feedContainer = document.getElementById('trackFeedContainer');
    if (!feedContainer) return;
    feedContainer.innerHTML = '';

    if (!data || data.length === 0) {
        feedContainer.innerHTML = `<div style="text-align:center; padding: 3rem 1rem; color: #64748b; font-size:0.88rem;">No active routes matched your filtered tracking profile parameters.</div>`;
        return;
    }

    data.forEach(route => {
        const card = document.createElement('div');
        card.className = 'track-card';
        card.id = `card-id-${route.route_id}`;
        
        let statusString = '';
        if (route.starred == 1) statusString += '<span style="color:#f59e0b;">★</span>';
        if (route.private == 1) statusString += '<span style="color:#64748b; margin-left:4px;">🔒</span>';

        card.innerHTML = `
            <div class="track-card-header">
                <h4 class="track-card-title">${route.name || 'Untitled Track'}</h4>
                <div style="display:flex; align-items:center; gap:6px;">
                    ${statusString}
                    <span class="discipline-pill discipline-${route.type || 1}">${routeTypeLabel(route.type)}</span>
                </div>
            </div>
            
            <div class="telemetry-row">
                <div class="telemetry-item">
                    <div class="telemetry-label">Distance</div>
                    <div class="telemetry-value">${route.distance_km ? Number(route.distance_km).toFixed(1) : '0.0'}<span>km</span></div>
                </div>
                <div class="telemetry-item">
                    <div class="telemetry-label">Elevation</div>
                    <div class="telemetry-value">${Math.round(route.elevation) || 0}<span>m</span></div>
                </div>
                <div class="telemetry-item">
                    <div class="telemetry-label">Time Est</div>
                    <div class="telemetry-value" style="font-size:0.8rem;">${formatDuration(route.estimated_moving_time)}</div>
                </div>
            </div>

            <div class="track-extended-panel" onclick="event.stopPropagation();">
                <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:#64748b; margin-bottom:8px;">
                    <span>Created: <strong>${route.created_date || '—'}</strong></span>
                    <span>Region: <strong>${route.country || 'Global'}</strong></span>
                </div>
                <div style="margin-bottom: 4px; font-size:0.72rem; text-transform:uppercase; font-weight:600; color:#64748b; letter-spacing:0.5px;">Workspace Classification Tags</div>
                <input type="text" class="tag-input-style" value="${route.tags || ''}" placeholder="Gravel, low-traffic, flat weekend..." onblur="saveTags('${route.route_id}', this.value)">
                <a href="https://www.strava.com/routes/${route.route_id}" target="_blank" style="display:block; margin-top:8px; font-size:0.75rem; text-align:right; color:#0284c7; text-decoration:none; font-weight:600;">View Profile Data on Strava ↗</a>
            </div>
        `;

        card.onclick = () => handleTrackSelection(route, card);
        feedContainer.appendChild(card);
    });
}

// Focus track selection controller
async function handleTrackSelection(route, cardElement) {
    document.querySelectorAll('.track-card').forEach(c => c.classList.remove('active-track'));
    cardElement.classList.add('active-track');

    // Remove old trace lines & points boundaries safely
    if (currentActivePolyline) globalWorkspaceMap.removeLayer(currentActivePolyline);
    currentDistanceMarkers.forEach(m => globalWorkspaceMap.removeLayer(m));
    currentDistanceMarkers = [];

    // Erase splash layout overlay configuration viewports
    document.getElementById('mapSplashHud').style.opacity = '0';

    if (!route.summary_polyline) {
        try {
            const res = await fetch(`get_polyline.php?route_id=${route.route_id}`);
            const polyData = await res.json();
            route.summary_polyline = polyData.polyline;
        } catch (e) { console.error("Dynamic track segment query runtime fault lines:", e); }
    }

    if (route.summary_polyline) {
        try {
            const coords = polyline.decode(route.summary_polyline).map(c => [c[0], c[1]]);
            currentActivePolyline = L.polyline(coords, { 
                color: '#ef4444', 
                weight: 4.5,
                opacity: 0.9,
                lineJoin: 'round'
            }).addTo(globalWorkspaceMap);
            
            globalWorkspaceMap.fitBounds(currentActivePolyline.getBounds(), {
                padding: [40, 40]
            });

            addDistanceMarkers(coords, 10);
        } catch (err) {
            console.error("Polyline canvas tracing calculations error boundary exception:", err);
        }
    }
}

function addDistanceMarkers(latlngs, stepKm = 10) {
    let distance = 0;
    let nextMarker = stepKm;

    for (let i = 1; i < latlngs.length; i++) {
        distance += haversineDistance(latlngs[i - 1], latlngs[i]);

        if (distance >= nextMarker) {
            const marker = L.circleMarker(latlngs[i], {
                radius: 4,
                color: '#0f172a',
                fillColor: '#ffffff',
                fillOpacity: 1,
                weight: 2
            }).addTo(globalWorkspaceMap);

            marker.bindTooltip(`${nextMarker} km`, {
                permanent: true,
                direction: 'top',
                className: 'distance-label',
                offset: [0, -5]
            });

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

// Post metadata parameters to operational state modifications adjustments array targets safely
async function saveTags(routeId, value) {
    const tags = value.split(',').map(t => t.trim()).filter(Boolean);
    try {
        const res = await fetch('save_route_tags.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ route_id: routeId, tags })
        });
        const data = await res.json();
        if (!data.success) alert(data.error || 'Failed to preserve workspace data tags modifications.');
    } catch (e) {
        alert('Server validation query tracking failure.');
    }
}

// Strava Synchronization Action Worker Loop
document.getElementById('fetchRoutes').addEventListener('click', async () => {
    const btn = document.getElementById('fetchRoutes');
    const originalText = btn.innerText;
    btn.disabled = true;

    let page = 1, keepGoing = true, totalSynced = 0;

    try {
        while (keepGoing) {
            btn.innerText = `Syncing P.${page} (${totalSynced} Tracks)`;
            const res = await fetch(`fetch_routes.php?page=${page}`);
            const data = await res.json();

            if (!data.success) throw new Error(data.error || 'Batch fetch execution parameters broke execution layers.');
            totalSynced += data.routes_in_batch;
            
            if (data.has_more) {
                page++;
                await new Promise(r => setTimeout(r, 1000));
            } else {
                keepGoing = false;
            }
        }

        btn.innerText = "Localizing Geography Elements...";
        let geocodingDone = false;
        while (!geocodingDone) {
            try {
                const geoRes = await fetch('sync_countries.php'); 
                const geoData = await geoRes.json();
                if (geoData.updated_count > 0) {
                    await new Promise(r => setTimeout(r, 400));
                } else {
                    geocodingDone = true;
                }
            } catch { geocodingDone = true; }
        }

        btn.innerText = "Success! Reloading Engine Context Grid...";
        setTimeout(() => location.reload(), 1000);
    } catch (e) {
        alert('Operational sync sequence failure exception logged: ' + e.message);
        btn.innerText = originalText;
        btn.disabled = false;
    }
});

// App Initialization Router Listener Loops
document.addEventListener('DOMContentLoaded', () => {
    initWorkspaceMap();
    renderTable(routes);
});
</script>
<?php include 'footer.php'; ?>
<?php exit(0); ?>
