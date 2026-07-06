<?php
/* ===============================
----Important rule going forward----
-Use case-              -ID to use-
DB queries              internal_user_id ✅
Strava API calls        strava_id
Session auth check      internal_user_id
================================ */
require_once 'config.php'; 

// Access control layer: kick them out to index if they aren't authenticated
if (!isset($_SESSION['internal_user_id'])) {
    header("Location: index.php");
    exit;
}
$internalUserId = $_SESSION['internal_user_id'];

// 1. Fetch unique countries
$countryStmt = $pdo->prepare("SELECT DISTINCT country FROM strava_routes WHERE user_id = ? AND country IS NOT NULL AND country != '' ORDER BY country ASC");
$countryStmt->execute([$internalUserId]);
$countries = $countryStmt->fetchAll(PDO::FETCH_COLUMN);

// 2. Fetch all routes
$stmt = $pdo->prepare("SELECT route_id, name, summary_polyline, distance_km, country FROM strava_routes WHERE user_id = ?");
$stmt->execute([$internalUserId]);
$allRoutes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include 'header.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>

<style>
    body {
        background-color: #f8fafc;
        font-family: 'Inter', sans-serif;
        color: #0f172a;
        margin: 0;
        padding: 2rem;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .header-block {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .header-title h1 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 0.25rem 0;
        letter-spacing: -0.5px;
    }

    .header-title p {
        font-size: 0.875rem;
        color: #64748b;
        margin: 0;
    }

    /* Core Action Controls Strip */
    .controls-panel {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 1.25rem;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .control-group {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .control-group label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #475569;
        white-space: nowrap;
    }

    /* Custom Sleek Inputs matching Routes styles */
    .select-input {
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        padding: 0.5rem 2rem 0.5rem 0.75rem;
        font-size: 0.875rem;
        font-family: inherit;
        color: #334155;
        outline: none;
        cursor: pointer;
        transition: border-color 0.2s ease;
    }
    .select-input:focus {
        border-color: #0284c7;
    }

    .range-slider {
        width: 150px;
        accent-color: #0284c7;
        cursor: pointer;
    }

    .badge-value {
        background-color: #e0f2fe;
        color: #0369a1;
        font-weight: 700;
        font-size: 0.875rem;
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
    }

    /* Actions Block and Action Pills */
    .actions-block {
        display: flex;
        gap: 0.5rem;
    }

    .btn-action-pill {
        display: inline-flex;
        align-items: center;
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        color: #475569;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        text-decoration: none;
        border-radius: 30px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .btn-action-pill:hover {
        background-color: #f8fafc;
        color: #0f172a;
        border-color: #cbd5e1;
    }

    /* Data Presentation Table */
    .table-container {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.03);
        overflow: hidden;
    }

    .custom-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
        font-size: 0.875rem;
    }

    .custom-table th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 600;
        padding: 0.875rem 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }

    .custom-table td {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }

    .custom-table tr:last-child td {
        border-bottom: none;
    }

    .custom-table tr:hover td {
        background-color: #f8fafc;
    }

    /* Modern action button for row maps */
    .btn-table-action {
        background-color: #ffffff;
        border: 1px solid #cbd5e1;
        color: #334155;
        padding: 0.375rem 0.75rem;
        font-size: 0.825rem;
        font-weight: 500;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .btn-table-action:hover {
        background-color: #0284c7;
        color: #ffffff;
        border-color: #0284c7;
    }

    /* Overlay Fullscreen Modal Map Layout */
    .modal-backdrop {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
    }

    .modal-content-container {
        background: #ffffff;
        margin: 4% auto;
        padding: 1.5rem;
        width: 85%;
        height: 78%;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        position: relative;
        display: flex;
        flex-direction: column;
    }

    .modal-close-btn {
        position: absolute;
        right: 1.5rem;
        top: 1rem;
        cursor: pointer;
        font-size: 1.75rem;
        color: #64748b;
        transition: color 0.2s ease;
        z-index: 10010;
    }
    .modal-close-btn:hover {
        color: #0f172a;
    }

    #compareMap {
        width: 100%;
        height: 100%;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    
    .progress-spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 50%;
        border-top-color: #0284c7;
        animation: spin 1s linear infinite;
        margin-right: 0.5rem;
        vertical-align: middle;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<div class="container">
    
    <div class="header-block">
        <div class="header-title">
            <h1>👥 Route Duplicate Finder</h1>
            <p>Analyze geographical overlap parameters between your logged sync tracks to isolate overlapping copies.</p>
        </div>
        <div class="actions-block">
            <a id="mapLink" href="routes.php" class="btn-action-pill">📊 Table View</a>
        </div>
    </div>

    <div class="controls-panel">
        <div class="control-group">
            <label for="overlapSlider">Minimum Overlap:</label>
            <input type="range" id="overlapSlider" class="range-slider" min="10" max="100" value="80">
            <span id="sliderVal" class="badge-value">80</span><span class="badge-value" style="margin-left:-2px; border-radius:0 6px 6px 0; padding-left:0;">%</span>
        </div>

        <div class="control-group">
            <label for="countryFilter">Country Scope:</label>
            <select id="countryFilter" class="select-input">
                <option value="all">All Countries (<?= count($allRoutes) ?> tracks)</option>
                <?php if (!empty($countries)): ?>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?= htmlspecialchars($country) ?>">
                            <?= htmlspecialchars($country) ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option disabled>No parameters found</option>
                <?php endif; ?>
            </select>
        </div>
    </div>

    <div class="table-container">
        <table id="duplicateTable" class="custom-table">
            <thead>
                <tr>
                    <th>Route Profile A</th>
                    <th>Route Profile B</th>
                    <th>Analytical Overlap</th>
                    <th style="width: 120px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody id="resultsBody"></tbody>
        </table>
    </div>

</div>

<div id="mapModal" class="modal-backdrop" onclick="closeMap()">
    <div class="modal-content-container" onclick="event.stopPropagation()">
        <span class="modal-close-btn" onclick="closeMap()">&times;</span>
        <div id="compareMap"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>
<script>
// --- DIAGNOSTIC LOGGING INITIALIZATION ---
console.log("🔍 DIAGNOSTIC: Script block execution started.");

const allRoutesData = <?= json_encode($allRoutes ?? []) ?>;
console.log("📥 DATABASE FETCH: Total raw route entries loaded from PHP:", allRoutesData.length);
if (allRoutesData.length > 0) {
    console.log("📥 DATABASE FETCH SAMPLE:", allRoutesData[0]);
}

// 1. Pre-process and decode map tracks with granular error catch blocks
const decodedRoutes = allRoutesData.map((r, idx) => {
    if (!r.summary_polyline || r.summary_polyline.length < 10) {
        console.warn(`⚠️ DECODE SKIP [Index ${idx}, ID ${r.route_id}]: Polyline string missing or too short.`);
        return null;
    }
    try {
        const points = polyline.decode(r.summary_polyline);
        if (!points || points.length === 0) {
            console.warn(`⚠️ DECODE SKIP [ID ${r.route_id}]: Polyline decoded into 0 coordinates.`);
            return null;
        }
        
        const lats = points.map(p => p[0]);
        const lons = points.map(p => p[1]);
        
        return {
            name: r.name,
            country: r.country,
            id: r.route_id,
            latlngs: points.map(p => L.latLng(p[0], p[1])),
            startPoint: [points[0][0], points[0][1]],
            minLat: Math.min(...lats),
            maxLat: Math.max(...lats),
            minLon: Math.min(...lons),
            maxLon: Math.max(...lons)
        };
    } catch (e) { 
        console.error(`🛑 CRITICAL DECODE FAILURE [ID ${r.route_id}]:`, e.message);
        return null; 
    }
}).filter(r => r !== null);

console.log("✅ DECODE SUCCESS: Geographically parsed profiles available for analysis:", decodedRoutes.length);

// --- COMPUTE ENGINES ---
function fastDist(p1, p2) {
    const dy = p1[0] - p2[0];
    const dx = p1[1] - p2[1];
    return Math.sqrt(dx*dx + dy*dy);
}

function haversineDistance(p1, p2) {
  const R = 6371000;
  const toRad = Math.PI / 180;
  const dLat = (p2[0] - p1[0]) * toRad;
  const dLon = (p2[1] - p1[1]) * toRad;
  const lat1 = p1[0] * toRad;
  const lat2 = p2[0] * toRad;

  const a = Math.sin(dLat/2)**2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon/2)**2;
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}

function pointToSegmentDistanceMeters(p, a, b) {
  const dx = b[1] - a[1];
  const dy = b[0] - a[0];
  if (dx === 0 && dy === 0) return haversineDistance(p, a);
  let t = ((p[1]-a[1])*dx + (p[0]-a[0])*dy)/(dx*dx + dy*dy);
  t = Math.max(0, Math.min(1, t));
  const proj = [a[0] + t*dy, a[1] + t*dx];
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
    
function findOverlap(latlngsA, latlngsB, tolerance = 8) {
    let total = 0;
    let overlap = 0;
    let segments = [];
    const step = 2; 

    for (let i = 0; i < latlngsA.length - 1; i += step) {
        const a1 = latlngsA[i];
        const a2 = latlngsA[i+1] || latlngsA[i];
        const segLen = haversineDistance([a1.lat, a1.lng], [a2.lat, a2.lng]);
        total += segLen;
        let matched = false;
        
        for (let j = 0; j < latlngsB.length - 1; j += step) {
            const b1 = latlngsB[j];
            if (Math.abs(a1.lat - b1.lat) > 0.002 || Math.abs(a1.lng - b1.lng) > 0.002) {
                continue; 
            }
            const b2 = latlngsB[j+1];
            if (segmentDistanceMeters([a1.lat, a1.lng], [a2.lat, a2.lng],
                                      [b1.lat, b1.lng], [b2.lat, b2.lng]) <= tolerance) {
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

// --- ANALYSIS LOOP ---
async function runDuplicateCheck() {
    console.log("⚙️ PIPELINE: runDuplicateCheck called.");
    isRunning = true;
    
    const sliderEl = document.getElementById('overlapSlider');
    const countryEl = document.getElementById('countryFilter');
    
    if (!sliderEl || !countryEl) {
        console.error("🛑 UI ERROR: Required form control element targets are missing from DOM.");
        return;
    }

    const threshold = parseInt(sliderEl.value);
    const selectedCountry = countryEl.value;
    console.log(`⚙️ PIPELINE: Starting pass with Threshold: ${threshold}%, Country Scope: ${selectedCountry}`);

    const tbody = document.getElementById('resultsBody');
    
    const activeRoutes = decodedRoutes.filter(r => {
        if (selectedCountry === "all") return true;
        return r.country === selectedCountry;
    });
    
    console.log(`⚙️ PIPELINE: Evaluation subset isolated. Matching pairs across ${activeRoutes.length} available targets.`);
    tbody.innerHTML = `<tr><td colspan='4' style='text-align:center; padding:30px; color:#64748b;'><div class='progress-spinner'></div> Analyzing cross-overlap configurations for ${activeRoutes.length} profiles... <strong id='progress' style='color:#0284c7;'>0</strong>%</td></tr>`;

    let html = "";
    const totalPairs = (activeRoutes.length * (activeRoutes.length - 1)) / 2;
    console.log(`⚙️ PIPELINE: Calculated total matrix comparisons to compute: ${totalPairs}`);
    
    if (totalPairs === 0) {
        tbody.innerHTML = `<tr><td colspan='4' style='text-align:center; padding:30px; color:#64748b;'>Not enough valid track logs available to build a comparison matrix.</td></tr>`;
        return;
    }
    
    let processedPairs = 0;
    let matchesCount = 0;

    for (let i = 0; i < activeRoutes.length; i++) {
        for (let j = i + 1; j < activeRoutes.length; j++) {
            if (!isRunning) {
                console.log("🛑 PIPELINE TERMINATED: Loop killed by execution flag swap.");
                return;
            }
            
            processedPairs++;
            if (processedPairs % 15 === 0) {
                const progressPct = Math.round((processedPairs / totalPairs) * 100);
                const progressIndicator = document.getElementById('progress');
                if (progressIndicator) progressIndicator.innerText = progressPct;
                await new Promise(r => setTimeout(r, 1));
            }

            const rA = activeRoutes[i];
            const rB = activeRoutes[j];

            // Distance & Matrix Box Guards
            if (fastDist(rA.startPoint, rB.startPoint) > 0.5) continue;
            if (rA.maxLat < rB.minLat || rA.minLat > rB.maxLat || rA.maxLon < rB.minLon || rA.minLon > rB.maxLon) continue;

            const resA = findOverlap(rA.latlngs, rB.latlngs);
            const resB = findOverlap(rB.latlngs, rA.latlngs);
            const finalPercent = Math.min(resA.percent, resB.percent);

            if (finalPercent >= threshold) {
                matchesCount++;
                html += `<tr>
                    <td style='font-weight: 500; color: #0f172a;'>${rA.name}</td>
                    <td style='font-weight: 500; color: #0f172a;'>${rB.name}</td>
                    <td><span class='badge-value'>${finalPercent.toFixed(1)}% match</span></td>
                    <td style='text-align:center;'>
                        <button class='btn-table-action' onclick="showComparison('${rA.id}', '${rB.id}')">🗺️ Map view</button>
                    </td>
                </tr>`;
            }
        }
    }
    
    console.log(`✅ PIPELINE FINISHED: Loop execution complete. Found ${matchesCount} duplicate matches.`);
    tbody.innerHTML = html || `<tr><td colspan='4' style='text-align:center; padding:30px; color:#64748b;'>No track duplicates identified matching a <b>>${threshold}%</b> baseline matrix requirement.</td></tr>`;
}

// --- INTERFACE INTERACTORS & LISTENERS ---
let isRunning = false;
let debounceTimer;

const sliderInput = document.getElementById('overlapSlider');
if (sliderInput) {
    console.log("🔗 INITIALIZATION: Attaching Event Handlers to Slider Control Element.");
    sliderInput.oninput = function() {
        const val = this.value;
        console.log("🎛️ USER ACTION: Slider dragged. New value tracking target:", val);
        
        const textIndicator = document.getElementById('sliderVal');
        if (textIndicator) {
            textIndicator.innerText = val;
        } else {
            console.error("🛑 UI ERROR: Span element #sliderVal not found in current view tree.");
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            console.log("🎛️ USER ACTION: Debounce timer fired. Resetting old pipeline run.");
            isRunning = false; 
            setTimeout(() => { runDuplicateCheck(); }, 10);
        }, 300);
    };
} else {
    console.error("🛑 CRITICAL INITIALIZATION ERROR: Element #overlapSlider is missing from the DOM markup.");
}

const countrySelector = document.getElementById('countryFilter');
if (countrySelector) {
    countrySelector.onchange = function() {
        console.log("🎛️ USER ACTION: Country filter adjusted to:", this.value);
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            isRunning = false;
            setTimeout(() => { runDuplicateCheck(); }, 10);
        }, 300);
    };
}

// Initial automatic boot sequence execution execution
window.addEventListener('DOMContentLoaded', () => {
    console.log("🚀 DOM LOADED: Triggering core matrix auto-run routine.");
    runDuplicateCheck();
});

let previewMap;

function showComparison(idA, idB) {
    console.log(`🗺️ MAP MODAL: Requesting cross-render for Track ${idA} vs Track ${idB}`);
    document.getElementById('mapModal').style.display = 'block';
    const rA = decodedRoutes.find(r => r.id === idA);
    const rB = decodedRoutes.find(r => r.id === idB);

    if (!rA || !rB) {
        console.error("🛑 MAP ERROR: Failed to isolate matching objects in memory array tracking definitions.");
        return;
    }

    if (!previewMap) {
        console.log("🗺️ MAP MODAL: Generating new Leaflet viewport canvas instance.");
        previewMap = L.map('compareMap');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(previewMap);
    } else {
        previewMap.eachLayer(layer => {
            if (layer instanceof L.Polyline) previewMap.removeLayer(layer);
        });
    }

    const lineA = L.polyline(rA.latlngs, {color: '#0284c7', weight: 4, opacity: 0.6}).addTo(previewMap);
    const lineB = L.polyline(rB.latlngs, {color: '#ef4444', weight: 4, opacity: 0.6}).addTo(previewMap);

    const matchData = findOverlap(rA.latlngs, rB.latlngs);
    const overlapSegments = matchData.segments || []; 
    console.log(`🗺️ MAP MODAL: Rendering ${overlapSegments.length} direct proximity match segments.`);
    
    overlapSegments.forEach(seg => {
        L.polyline(seg, {color: '#22c55e', weight: 6, opacity: 1}).addTo(previewMap);
    });

    const group = new L.featureGroup([lineA, lineB]);
    previewMap.fitBounds(group.getBounds(), {padding: [40, 40]});
    setTimeout(() => { previewMap.invalidateSize(); }, 200);
}

function closeMap() {
    document.getElementById('mapModal').style.display = 'none';
}
</script>