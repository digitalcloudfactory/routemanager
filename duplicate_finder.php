<?php
/* ===============================
----Important rule going forward----
-Use case-              -ID to use-
DB queries              internal_user_id ✅
Strava API calls        strava_id
Session auth check      internal_user_id
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

// 1. Fetch unique countries
$countryStmt = $pdo->prepare("SELECT DISTINCT country FROM strava_routes WHERE user_id = ? AND country IS NOT NULL AND country != '' ORDER BY country ASC");
$countryStmt->execute([$internalUserId]);
$countries = $countryStmt->fetchAll(PDO::FETCH_COLUMN);

?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>
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
    -webkit-font-smoothing: antialiased;
}

main.container {
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
}

/* Floating Clean Dashboard Panel */
header.grid {
    position: relative;
    margin: 20px 20px 0 20px;
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
    gap: 8px;
    align-items: center;
}

/* Action Pill Buttons */
.btn-action-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.35rem 0.65rem;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #1e293b;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15 ease;
}
.btn-action-pill:hover {
    border-color: #0284c7;
    background: #f0f9ff;
    color: #0284c7;
}

/* Content Container */
.content-wrapper {
    padding: 20px;
}

/* Control Controls Bar */
.controls-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem 1.25rem;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.control-group {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 0.85rem;
    font-weight: 500;
    color: #334155;
}

.control-group select {
    padding: 0.4rem 0.75rem;
    font-size: 0.85rem;
    font-family: 'Inter', sans-serif;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    background-color: #f8fafc;
    color: #0f172a;
    outline: none;
}
.control-group select:focus {
    border-color: #0284c7;
}

.range-slider {
    display: flex;
    align-items: center;
    gap: 8px;
}
.range-slider input[type="range"] {
    accent-color: #0284c7;
    cursor: pointer;
}

/* Styled Table */
.table-container {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
    overflow: hidden;
}

table.styled-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    text-align: left;
}

table.styled-table thead tr {
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    color: #475569;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.725rem;
    letter-spacing: 0.05em;
}

table.styled-table th,
table.styled-table td {
    padding: 0.85rem 1.25rem;
}

table.styled-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.15s ease;
}

table.styled-table tbody tr:hover {
    background-color: #f8fafc;
}

table.styled-table tbody tr:last-of-type {
    border-bottom: none;
}

.match-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    background: #f0f9ff;
    color: #0284c7;
    font-weight: 700;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.8rem;
    border: 1px solid #bae6fd;
}

/* Modal Styling */
.modal-overlay {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
}

.modal-content {
    background: #ffffff;
    margin: 3% auto;
    width: 85%;
    height: 85%;
    border-radius: 12px;
    position: relative;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 0.8rem 1.25rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8fafc;
}

.modal-close-btn {
    cursor: pointer;
    font-size: 1.25rem;
    color: #64748b;
    border: none;
    background: transparent;
    transition: color 0.15s ease;
}
.modal-close-btn:hover {
    color: #0f172a;
}

#compareMap {
    width: 100%;
    height: 100%;
    z-index: 1;
}

/* Fix Leaflet Zoom Control alignment inside modal */
.leaflet-control-zoom a {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    line-height: 1 !important;
    padding: 0 !important;
    text-decoration: none !important;
    font-size: 18px !important;
}
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
        <a href="map.php" class="btn-action-pill">🗺️ Map View</a>
    </div>    
</header>

<div class="content-wrapper">
    <div class="controls-card">
        <div style="font-weight: 700; font-size: 1.05rem; color: #0f172a; display: flex; align-items: center; gap: 6px;">
            👥 Route Duplicate Finder
        </div>
        
        <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
            <div class="control-group range-slider">
                <span>Min Match:</span>
                <input type="range" id="overlapSlider" min="10" max="100" value="80"> 
                <span id="sliderVal" class="match-badge">80%</span>
            </div>

            <div class="control-group">
                <span>Country:</span>
                <select id="countryFilter">
                    <option value="all">All Countries</option>
                    <?php if (!empty($countries)): ?>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= htmlspecialchars($country) ?>">
                                <?= htmlspecialchars($country) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option disabled>No countries found in DB</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="table-container">
        <table id="duplicateTable" class="styled-table">
            <thead>
                <tr>
                    <th>Route A</th>
                    <th>Route B</th>
                    <th>Overlap %</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody id="resultsBody">
                <tr>
                    <td colspan="4" style="text-align:center; padding:30px; color: #64748b;">
                        Adjust parameters or wait for automated comparison scan...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div id="mapModal" class="modal-overlay" onclick="closeMap()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span style="font-weight: 600; font-size: 0.9rem; color: #0f172a;">Route Comparison Map</span>
            <button class="modal-close-btn" onclick="closeMap()">&times;</button>
        </div>
        <div id="compareMap"></div>
    </div>
</div>




<script>
// =============================================================
// GLOBAL STATE & LOGGING HELPER
// =============================================================
let rawRoutesData = [];
let decodedRoutes = [];
let currentExecutionId = 0;
let debounceTimer = null;
let previewMap = null;

function logStage(stage, message, details = null) {
    const time = new Date().toLocaleTimeString();
    const style = 'background: #0284c7; color: #fff; padding: 2px 6px; border-radius: 4px; font-weight: bold;';
    if (details !== null) {
        console.log(`%c[${time}] [${stage}]`, style, message, details);
    } else {
        console.log(`%c[${time}] [${stage}]`, style, message);
    }
}

// =============================================================
// STAGE 1 & 2: ASYNC BACKGROUND FETCH & DECODING
// =============================================================
async function loadRoutesFromAPI(selectedCountry = 'all') {
    logStage('1. FETCH START', `Requesting routes from get_map_routes.php (Country filter: "${selectedCountry}")...`);
    
    const tbody = document.getElementById('resultsBody');
    if (tbody) {
        tbody.innerHTML = `<tr><td colspan='4' style='text-align:center; padding:30px; color: #0284c7; font-weight:600;'>⏳ Loading route payload in background...</td></tr>`;
    }

    try {
        const response = await fetch(`get_map_routes.php?country=${encodeURIComponent(selectedCountry)}`);
        if (!response.ok) throw new Error(`HTTP Error ${response.status}`);
        
        rawRoutesData = await response.json();
        logStage('1. FETCH COMPLETE', `Successfully downloaded ${rawRoutesData.length} routes from background API.`);

        if (rawRoutesData.length > 0) {
            console.log("🔍 Sample route object structure from API:", rawRoutesData[0]);
        }

        // Only update the "All Countries" counter on full fetches
        if (selectedCountry === 'all') {
            const countrySelect = document.getElementById('countryFilter');
            if (countrySelect && countrySelect.options.length > 0) {
                countrySelect.options[0].text = `All Countries (${rawRoutesData.length} routes)`;
            }
        }

        // Decode polylines in memory
        console.groupCollapsed('🔍 Polyline Decoding Engine');
        let skippedCount = 0;

        decodedRoutes = rawRoutesData.map((r, idx) => {
            // Check for potential polyline property names
            const polyStr = r.summary_polyline || r.polyline || r.summaryPolyline;

            if (!polyStr || typeof polyStr !== 'string' || polyStr.length < 5) {
                if (idx < 5) console.warn(`Route [${r.name || r.route_id}] missing valid polyline string:`, polyStr);
                skippedCount++;
                return null;
            }

            try {
                const points = polyline.decode(polyStr);
                if (!points || points.length < 2) {
                    skippedCount++;
                    return null;
                }
                
                let minLat = Infinity, maxLat = -Infinity;
                let minLon = Infinity, maxLon = -Infinity;

                const coords = points.map(p => {
                    const lat = p[0], lon = p[1];
                    if (lat < minLat) minLat = lat;
                    if (lat > maxLat) maxLat = lat;
                    if (lon < minLon) minLon = lon;
                    if (lon > maxLon) maxLon = lon;
                    return [lat, lon];
                });

                return {
                    name: r.name || 'Unnamed Route',
                    country: r.country || '',
                    id: r.route_id,
                    coords: coords,
                    minLat, maxLat, minLon, maxLon
                };
            } catch (e) { 
                if (idx < 5) console.error(`Error decoding polyline for route [${r.route_id}]:`, e);
                skippedCount++;
                return null; 
            }
        }).filter(r => r !== null);

        console.groupEnd();
        logStage('2. DECODE COMPLETE', `Decoded ${decodedRoutes.length} valid route paths. Skipped/Invalid: ${skippedCount}.`);

        // Trigger analysis now that data is ready
        runDuplicateCheck();

    } catch (error) {
        logStage('ERROR', 'Failed to fetch routes from API:', error);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan='4' style='text-align:center; padding:30px; color: #ef4444;'>❌ Failed to load routes: ${error.message}</td></tr>`;
        }
    }
}

// =============================================================
// SPATIAL GEOMETRY CALCULATORS
// =============================================================
function haversineDistance(p1, p2) {
    const R = 6371000;
    const toRad = Math.PI / 180;
    const dLat = (p2[0] - p1[0]) * toRad;
    const dLon = (p2[1] - p1[1]) * toRad;
    const lat1 = p1[0] * toRad;
    const lat2 = p2[0] * toRad;

    const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function pointToSegmentDistanceMeters(p, a, b) {
    const dx = b[1] - a[1];
    const dy = b[0] - a[0];
    if (dx === 0 && dy === 0) return haversineDistance(p, a);

    let t = ((p[1] - a[1]) * dx + (p[0] - a[0]) * dy) / (dx * dx + dy * dy);
    t = Math.max(0, Math.min(1, t));

    const proj = [a[0] + t * dy, a[1] + t * dx];
    return haversineDistance(p, proj);
}

function segmentDistanceMeters(p1a, p1b, p2a, p2b) {
    return Math.min(
        pointToSegmentDistanceMeters(p1a, p2a, p2b),
        pointToSegmentDistanceMeters(p1b, p2a, p2b),
        pointToSegmentDistanceMeters(p2a, p1a, p1b),
        pointToSegmentDistanceMeters(p2b, p1a, p1b)
    );
}

function findOverlap(coordsA, coordsB, tolerance = 10) {
    let total = 0;
    let overlap = 0;
    let segments = [];
    const step = 2; 

    for (let i = 0; i < coordsA.length - 1; i += step) {
        const a1 = coordsA[i];
        const a2 = coordsA[i + 1] || coordsA[i];
        const segLen = haversineDistance(a1, a2);
        total += segLen;

        let matched = false;
        for (let j = 0; j < coordsB.length - 1; j += step) {
            const b1 = coordsB[j];
            if (Math.abs(a1[0] - b1[0]) > 0.003 || Math.abs(a1[1] - b1[1]) > 0.003) continue;

            const b2 = coordsB[j + 1] || coordsB[j];
            if (segmentDistanceMeters(a1, a2, b1, b2) <= tolerance) {
                matched = true;
                break;
            }
        }

        if (matched) {
            overlap += segLen;
            segments.push([a1, a2]);
        }
    }

    return { 
        percent: total > 0 ? (overlap / total) * 100 : 0, 
        segments: segments
    };
}

// =============================================================
// STAGE 3: NON-BLOCKING DUPLICATE SCAN ENGINE
// =============================================================
async function runDuplicateCheck() {
    const executionId = ++currentExecutionId;
    
    // SAFE DOM ACCESS WITH FALLBACKS
    const sliderElem = document.getElementById('overlapSlider');
    const countryElem = document.getElementById('countryFilter');
    
    const threshold = sliderElem ? parseInt(sliderElem.value, 10) : 50;
    const selectedCountry = countryElem ? countryElem.value : 'all';
    const tbody = document.getElementById('resultsBody');
    
    logStage('3. SCAN STARTED', `Analyzing ${decodedRoutes.length} routes. Min Overlap: ${threshold}%, Country: "${selectedCountry}" (Exec ID: #${executionId})`);

    if (!tbody) {
        console.error("CRITICAL: Element #resultsBody not found in DOM!");
        return;
    }

    const activeRoutes = decodedRoutes.filter(r => {
        if (selectedCountry === "all") return true;
        return r.country === selectedCountry;
    });

    if (activeRoutes.length < 2) {
        logStage('3. SCAN CANCELLED', 'Fewer than 2 routes available to compare.');
        tbody.innerHTML = `<tr><td colspan='4' style='text-align:center; padding:30px; color: #64748b;'>Not enough routes in selected filter to compare (requires at least 2).</td></tr>`;
        return;
    }

    tbody.innerHTML = `<tr><td colspan='4' style='text-align:center; padding:30px; color: #64748b;'>Checking ${activeRoutes.length} routes... <span id='progress' style='font-weight:700; color:#0284c7;'>0</span>%</td></tr>`;

    let html = "";
    const totalPairs = (activeRoutes.length * (activeRoutes.length - 1)) / 2;
    let processedPairs = 0;
    let boundingBoxPruned = 0;
    let matchesFound = 0;

    console.groupCollapsed(`⚡ Comparison Matrix (${totalPairs} total pairs)`);

    for (let i = 0; i < activeRoutes.length; i++) {
        for (let j = i + 1; j < activeRoutes.length; j++) {
            if (executionId !== currentExecutionId) {
                console.groupEnd();
                logStage('3. SCAN ABORTED', `Execution #${executionId} cancelled by a new UI filter change.`);
                return;
            }
            
            processedPairs++;
            
            // Yield execution back to the browser every 10 pairs so UI stays responsive and console logs output instantly
            if (processedPairs % 10 === 0) {
                const progElem = document.getElementById('progress');
                if (progElem) progElem.innerText = Math.round((processedPairs / totalPairs) * 100);
                await new Promise(r => setTimeout(r, 0));
            }

            const rA = activeRoutes[i];
            const rB = activeRoutes[j];

            // Fast Bounding Box Pre-Check
            const buf = 0.005;
            if (rA.maxLat + buf < rB.minLat || rA.minLat - buf > rB.maxLat || 
                rA.maxLon + buf < rB.minLon || rA.minLon - buf > rB.maxLon) {
                boundingBoxPruned++;
                continue;
            }

            const resA = findOverlap(rA.coords, rB.coords);
            const resB = findOverlap(rB.coords, rA.coords);
            const finalPercent = Math.min(resA.percent, resB.percent);

            console.log(`Comparing "${rA.name}" vs "${rB.name}" ➔ Overlap: ${finalPercent.toFixed(1)}%`);

            if (finalPercent >= threshold) {
                matchesFound++;
                console.log(`   └─ ✅ DUPLICATE MATCH FOUND (≥ ${threshold}%)`);
                html += `<tr>
                    <td style="font-weight: 600; color: #0f172a;">${rA.name}</td>
                    <td style="font-weight: 600; color: #0f172a;">${rB.name}</td>
                    <td><span class="match-badge">${finalPercent.toFixed(1)}%</span></td>
                    <td style="text-align: right;">
                        <button class="btn-action-pill" onclick="showComparison('${rA.id}', '${rB.id}')">🗺️ View Map</button>
                    </td>
                </tr>`;
            }
        }
    }
    
    console.groupEnd();

    if (executionId === currentExecutionId) {
        logStage('3. SCAN FINISHED', `Completed ${processedPairs} pair evaluations. Pruned ${boundingBoxPruned} non-overlapping routes via bounding box. Found ${matchesFound} matches.`);
        tbody.innerHTML = html || `<tr><td colspan='4' style='text-align:center; padding:30px; color:#64748b;'>No duplicate routes found above ${threshold}%.</td></tr>`;
    }
}

// =============================================================
// STAGE 4: USER EVENTS (COUNTRY & SLIDER CHANGES)
// =============================================================
function triggerDebouncedCheck(sourceEvent) {
    logStage('USER EVENT', `Action triggered by: [${sourceEvent}]. Debouncing (300ms)...`);
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        runDuplicateCheck();
    }, 300);
}

// =============================================================
// STAGE 5: MAP MODAL PREVIEW
// =============================================================
function showComparison(idA, idB) {
    const rA = decodedRoutes.find(r => r.id === idA);
    const rB = decodedRoutes.find(r => r.id === idB);

    logStage('MAP MODAL', `Opening modal for Route A ("${rA?.name}") vs Route B ("${rB?.name}")`);

    document.getElementById('mapModal').style.display = 'block';

    if (!rA || !rB) return;

    if (!previewMap) {
        logStage('MAP MODAL', 'Initializing Leaflet map instance...');
        previewMap = L.map('compareMap');
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap contributors &copy; CARTO'
        }).addTo(previewMap);
    } else {
        previewMap.eachLayer(layer => {
            if (layer instanceof L.Polyline) previewMap.removeLayer(layer);
        });
    }

    const lineA = L.polyline(rA.coords, {color: '#0284c7', weight: 4, opacity: 0.6}).addTo(previewMap);
    const lineB = L.polyline(rB.coords, {color: '#e11d48', weight: 4, opacity: 0.6}).addTo(previewMap);

    const matchData = findOverlap(rA.coords, rB.coords);
    (matchData.segments || []).forEach(seg => {
        L.polyline(seg, {color: '#10b981', weight: 6, opacity: 1}).addTo(previewMap);
    });

    const group = L.featureGroup([lineA, lineB]);
    previewMap.fitBounds(group.getBounds(), {padding: [30, 30]});
    
    setTimeout(() => { previewMap.invalidateSize(); }, 200);
}

function closeMap() {
    logStage('MAP MODAL', 'Closing map modal.');
    document.getElementById('mapModal').style.display = 'none';
}

// =============================================================
// DOM INIT & EVENT LISTENERS
// =============================================================
document.addEventListener('DOMContentLoaded', () => {
    logStage('DOM READY', 'Page loaded. Initializing event handlers and requesting background routes...');
    
    const countrySelect = document.getElementById('countryFilter');
    const slider = document.getElementById('overlapSlider');

    if (countrySelect) {
        countrySelect.addEventListener('change', (e) => {
            const selected = e.target.value;
            logStage('USER EVENT', `Country dropdown switched to: "${selected}"`);
            
            // Re-fetch or re-filter dynamically
            loadRoutesFromAPI(selected);
        });
    }

    if (slider) {
        slider.addEventListener('input', function(e) {
            document.getElementById('sliderVal').innerText = this.value + '%';
            triggerDebouncedCheck('Overlap Slider');
        });
    }

    // Trigger initial background fetch
    loadRoutesFromAPI('all');
});
</script>


<?php include 'footer.php'; ?>