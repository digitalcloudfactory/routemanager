<?php
session_start();

ini_set('memory_limit', '512M'); 
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

$countryStmt = $pdo->prepare("SELECT DISTINCT country FROM strava_routes WHERE user_id = ? AND country IS NOT NULL AND country != '' ORDER BY country ASC");
$countryStmt->execute([$internalUserId]);
$countries = $countryStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">

<script src="routes_shared.js?v=1.0.1"></script>

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

#map {
    display: block !important;
    visibility: visible !important;
    position: fixed !important;
    top: 0;
    left: 0;
    width: 100vw !important;
    height: 100vh !important;
    margin: 0 !important;
    padding: 0 !important;
    border-radius: 0 !important;
    z-index: 1 !important;
}

.leaflet-control-container {
    z-index: 500 !important;
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

main.container {
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* Floating Clean Dashboard Panel over Canvas Layers */
header.grid {
    position: absolute !important;
    top: 20px;
    left: 20px;
    right: 20px;
    z-index: 1000 !important;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    padding: 0.6rem 1rem !important;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 12px 40px rgba(15, 23, 42, 0.04);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.user-meta-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 0.15rem;
}

.profile-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f8fafc;
    padding: 0.15rem 0.5rem 0.15rem 0.15rem;
    border-radius: 50px;
    border: 1px solid #e2e8f0;
}
.profile-badge img {
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #0284c7;
}

.actions-block {
    display: flex;
    gap: 6px;
    align-items: center;
}

/* Custom Component Elements Framework Layout */
.btn-action-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.35rem 0.6rem;
    border-radius: 6px;
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

.range-slider input[type="range"]::-webkit-slider-thumb { pointer-events: auto; cursor: pointer; }
.range-slider input[type="range"]::-moz-range-thumb { pointer-events: auto; cursor: pointer; }
#filterDistanceMin { background: transparent !important; }       
</style>

<main class="container">
<header class="grid">
    <div>
            <div class="user-meta-bar">
                <div class="profile-badge">
                    <img src="<?= htmlspecialchars($user['avatar'] ?? '') ?>" alt="Avatar" width="16" height="16">
                    <span style="font-size: 0.7rem; font-weight: 600; color: #334155;"><?= htmlspecialchars(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')) ?></span>
                </div>
                <div style="font-size: 0.68rem; color: #64748b;">
                    Last Sync: <span style="font-weight:600; color:#1e293b;"><?= !empty($user['last_routes_sync']) ? htmlspecialchars($user['last_routes_sync']) : 'Never' ?></span>
                </div>
            </div>
        </div>

    <div class="actions-block">
        <a id="mapLink" href="routes.php" class="btn-action-pill">📊 Table View</a>
        <button id="openFilters" class="btn-action-pill" type="button">⚙️ Filters</button>
        <button id="fetchRoutes" class="btn-action-pill btn-sync" type="button">🚀 Sync Tracks</button>
    </div>    
</header>

<section style="margin: 0; padding: 0;">
    <div id="map"></div>
</section>

  <?php include 'filter_panel.php'; ?>
</main>

<script>
const chunkSize = 50;
let currentIndex = 0;
let currentRenderSet = []; 
let routes = []; 

const map = L.map('map', { trackResize: true, zoomControl: false }).setView([50.8503, 4.3517], 9);
L.control.zoom({ position: 'topright' }).addTo(map);

L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
    attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
}).addTo(map);

const routeBoundsGroup = L.featureGroup().addTo(map);

function drawRoutes(targetArray) {
    routeBoundsGroup.clearLayers();
    currentRenderSet = targetArray;
    currentIndex = 0;
    renderNextChunk();
}

function renderNextChunk() {
    const end = Math.min(currentIndex + chunkSize, currentRenderSet.length);
    
    for (let i = currentIndex; i < end; i++) {
        const route = currentRenderSet[i];
        
        if (route.summary_polyline) {
            try {
                const decodedPoints = polyline.decode(route.summary_polyline);
                
                const line = L.polyline(decodedPoints, { 
                    color: '#0284c7', 
                    weight: 3, 
                    opacity: 0.65 
                });

                const distance = route.distance_km ? Number(route.distance_km).toFixed(1) : '0.0';
                const elevation = route.elevation ? Math.round(Number(route.elevation)) : '0';
                const dateCreated = route.created_date || 'Unknown date';
                
                const tagsHTML = route.tags 
                    ? `<div style="margin-top: 6px;"><small style="background: #f1f5f9; padding: 3px 8px; border-radius: 4px; color: #475569; font-weight:500;">🏷️ ${route.tags}</small></div>` 
                    : '';

                line.bindPopup([
                    `<div style="font-family: 'Inter', sans-serif; font-size: 13px; line-height: 1.5; color: #1e293b; padding: 2px;">`,
                        `<strong style="font-size: 14px; color: #0f172a; display: block; margin-bottom: 2px;">${route.name}</strong>`,
                        `<span style="color: #94a3b8; font-size: 11px;">📅 ${dateCreated}</span>`,
                        `<hr style="margin: 8px 0; border: 0; border-top: 1px solid #e2e8f0;">`,
                        `<strong>📏 Distance:</strong> ${distance} km<br>`,
                        `<strong>⛰️ Elevation:</strong> ${elevation} m<br>`,
                        tagsHTML,
                    `</div>`
                ].join(''));

                line.addTo(routeBoundsGroup);
                
            } catch (e) {
                console.error("Failed to parse polyline for route ID:", route.route_id, e);
            }
        }
    }
    
    currentIndex = end;
    
    if (currentIndex < currentRenderSet.length) {
        setTimeout(renderNextChunk, 10);
    } else {
        if (routeBoundsGroup.getLayers().length > 0) {
            map.fitBounds(routeBoundsGroup.getBounds(), { padding: [50, 50] });
        }
    }
}

fetch('get_map_routes.php')
    .then(response => response.json())
    .then(data => {
        routes = data;
        if (typeof applyFilters === 'function') {
            applyFilters(); 
        } else {
            drawRoutes(routes); 
        }
    })
    .catch(error => console.error('Error loading API tracks route payload:', error));

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
        btn.innerText = "Success! Reloading...";
        setTimeout(() => location.reload(), 1000);
    } catch (e) {
        alert('Sync faulted: ' + e.message);
        btn.disabled = false;
        btn.innerText = "🚀 Sync Tracks";
    }
});

// Master Application Synchronization Bootstrapper for map.php
document.addEventListener('DOMContentLoaded', () => {
    // 1. Instantly consume and pre-populate your view state variables from URL params
    const initialParams = new URLSearchParams(window.location.search);
    
    if (initialParams.has('country') && document.getElementById('filterCountry')) {
        document.getElementById('filterCountry').value = initialParams.get('country');
    }
    if (initialParams.has('search') && document.getElementById('filterSearch')) {
        document.getElementById('filterSearch').value = initialParams.get('search');
    }
    if (initialParams.has('dist_min') && document.getElementById('filterDistanceMin')) {
        document.getElementById('filterDistanceMin').value = initialParams.get('dist_min');
    }
    if (initialParams.has('dist_max') && document.getElementById('filterDistanceMax')) {
        document.getElementById('filterDistanceMax').value = initialParams.get('dist_max');
    }

    // 2. Map-Specific Step: Async Remote Database Payload Sync
    fetch('get_map_routes.php')
        .then(response => response.json())
        .then(data => {
            routes = data;
            if (typeof applyFilters === 'function') {
                applyFilters(); 
            } else {
                drawRoutes(routes); 
            }
        })
        .catch(error => console.error('Error loading API tracks route payload:', error));

    // 3. REAL-TIME URL BAR SYNCHRONIZER
    function syncFiltersToURLBar() {
        const currentParams = new URLSearchParams();
        const countryEl = document.getElementById('filterCountry');
        const searchEl  = document.getElementById('filterSearch');
        const distMinEl = document.getElementById('filterDistanceMin');
        const distMaxEl = document.getElementById('filterDistanceMax');

        if (countryEl && countryEl.value) currentParams.set('country', countryEl.value);
        if (searchEl  && searchEl.value)  currentParams.set('search', searchEl.value);
        if (distMinEl && distMinEl.value) currentParams.set('dist_min', distMinEl.value);
        if (distMaxEl && distMaxEl.value) currentParams.set('dist_max', distMaxEl.value);

        const newRelativePathQuery = window.location.pathname + '?' + currentParams.toString();
        history.replaceState(null, '', newRelativePathQuery);
    }

    // Attach listeners to all standard slider inputs and filter nodes
    ['filterCountry', 'filterSearch', 'filterDistanceMin', 'filterDistanceMax'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', syncFiltersToURLBar);
            el.addEventListener('change', syncFiltersToURLBar);
        }
    });

    // 4. LIVE TOGGLE PARAMETER INTERCEPTOR (For routing back to routes.php cleanly)
    const viewToggleLink = document.getElementById('mapLink');
    if (viewToggleLink) {
        viewToggleLink.addEventListener('click', (e) => {
            e.preventDefault();
            const targetUrl = new URL(viewToggleLink.getAttribute('href'), window.location.origin);
            const currentParams = new URLSearchParams();

            const countryEl = document.getElementById('filterCountry');
            const searchEl  = document.getElementById('filterSearch');
            const distMinEl = document.getElementById('filterDistanceMin');
            const distMaxEl = document.getElementById('filterDistanceMax');

            if (countryEl && countryEl.value) currentParams.set('country', countryEl.value);
            if (searchEl  && searchEl.value)  currentParams.set('search', searchEl.value);
            if (distMinEl && distMinEl.value) currentParams.set('dist_min', distMinEl.value);
            if (distMaxEl && distMaxEl.value) currentParams.set('dist_max', distMaxEl.value);

            window.location.href = `${targetUrl.pathname}?${currentParams.toString()}`;
        });
    }
});
</script>

<?php include 'footer.php'; ?>
<?php exit(0); ?>
