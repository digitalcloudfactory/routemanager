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
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>Route Duplicate Finder</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/@mapbox/polyline"></script>

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
        }
        .btn-action-pill:hover {
            background-color: #f8fafc;
            color: #0f172a;
            border-color: #cbd5e1;
        }

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
            position: relative;
        }

        .modal-close-btn {
            position: absolute;
            right: 1.5rem;
            top: 1rem;
            cursor: pointer;
            font-size: 1.75rem;
            color: #64748b;
        }

        #compareMap {
            width: 100%;
            height: 100%;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>

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
            <span id="sliderVal" class="badge-value">80</span><span class="badge-value" style="margin-left:-2px;">%</span>
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
            <tbody id="resultsBody">
                <tr>
                    <td colspan="4" style="text-align:center; padding:30px; color:#64748b;">Initializing calculation engine...</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

<div id="mapModal" class="modal-backdrop" onclick="closeMap()">
    <div class="modal-content-container" onclick="event.stopPropagation()">
        <span class="modal-close-btn" onclick="closeMap()">&times;</span>
        <div id="compareMap"></div>
    </div>
</div>

<div id="visualEngineLog" style="margin-top: 20px; padding: 15px; background: #0f172a; color: #38bdf8; font-family: monospace; font-size: 12px; border-radius: 8px; line-height: 1.6; max-height: 300px; overflow-y: auto; box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);">
    <div style="color: #94a3b8; border-bottom: 1px solid #334155; padding-bottom: 4px; margin-bottom: 8px; font-weight: bold;">📺 REALTIME CALCULATOR LOG DISPLAY:</div>
</div>

<script>
// --- CORE UI LOGGING INTERCEPTOR ---
function uiLog(message, isError = false) {
    const logBox = document.getElementById('visualEngineLog');
    if (logBox) {
        const color = isError ? '#ef4444' : '#38bdf8';
        const prefix = isError ? '🛑 [ERROR]' : '🔍 [LOG]';
        logBox.innerHTML += `<div style="color: ${color}">${prefix} ${message}</div>`;
        logBox.scrollTop = logBox.scrollHeight; // Auto-scroll to bottom
    }
    console.log(message);
}

uiLog("Script block execution started.");

// 1. Data ingestion check
let allRoutesData = [];
try {
    allRoutesData = <?= json_encode($allRoutes ?? []) ?>;
    uiLog(`Data array received from database. Total rows: ${allRoutesData.length}`);
} catch(phpErr) {
    uiLog(`PHP Data parsing exception: ${phpErr.message}`, true);
}

// 2. Pre-process mapping loop
uiLog("Beginning polyline array compilation layer...");
const decodedRoutes = allRoutesData.map((r, idx) => {
    if (!r.summary_polyline || r.summary_polyline.length < 10) return null;
    try {
        const points = polyline.decode(r.summary_polyline);
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
    } catch (e) { return null; }
}).filter(r => r !== null);

uiLog(`Data processing loop complete. Valid active routes in memory: ${decodedRoutes.length}`);

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
  return R * (2 * Math.atan2(
    Math.sqrt(Math.sin(dLat/2)**2 + Math.cos(p1[0]*toRad) * Math.cos(p2[0]*toRad) * Math.sin(dLon/2)**2),
    Math.sqrt(1 - (Math.sin(dLat/2)**2 + Math.cos(p1[0]*toRad) * Math.cos(p2[0]*toRad) * Math.sin(dLon/2)**2))
  ));
}

function pointToSegmentDistanceMeters(p, a, b) {
  const dx = b[1] - a[1];
  const dy = b[0] - a[0];
  if (dx === 0 && dy === 0) return haversineDistance(p, a);
  let t = ((p[1]-a[1])*dx + (p[0]-a[0])*dy)/(dx*dx + dy*dy);
  t = Math.max(0, Math.min(1, t));
  return haversineDistance(p, [a[0] + t*dy, a[1] + t*dx]);
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
    let total = 0, overlap = 0, segments = [], step = 2; 
    for (let i = 0; i < latlngsA.length - 1; i += step) {
        const a1 = latlngsA[i], a2 = latlngsA[i+1] || a1;
        const segLen = haversineDistance([a1.lat, a1.lng], [a2.lat, a2.lng]);
        total += segLen;
        let matched = false;
        for (let j = 0; j < latlngsB.length - 1; j += step) {
            const b1 = latlngsB[j];
            if (Math.abs(a1.lat - b1.lat) > 0.002 || Math.abs(a1.lng - b1.lng) > 0.002) continue; 
            const b2 = latlngsB[j+1];
            if (segmentDistanceMeters([a1.lat, a1.lng], [a2.lat, a2.lng], [b1.lat, b1.lng], [b2.lat, b2.lng]) <= tolerance) {
                matched = true;
                break;
            }
        }
        if (matched) { overlap += segLen; segments.push([a1, a2]); }
    }
    return { percent: total > 0 ? (overlap / total) * 100 : 0, segments: segments };
}

async function runDuplicateCheck() {
    uiLog("Invoking runDuplicateCheck calculation matrix...");
    isRunning = true;
    
    const sliderEl = document.getElementById('overlapSlider');
    const countryEl = document.getElementById('countryFilter');
    const tbody = document.getElementById('resultsBody');
    
    if (!sliderEl || !countryEl || !tbody) {
        uiLog("DOM connection layout components missing or unreachable.", true);
        return;
    }

    const threshold = parseInt(sliderEl.value);
    const selectedCountry = countryEl.value;
    
    const activeRoutes = decodedRoutes.filter(r => {
        if (selectedCountry === "all") return true;
        return r.country === selectedCountry;
    });
    
    uiLog(`Filtering applied. Active profiles: ${activeRoutes.length} (Target Cutoff: ${threshold}%)`);
    tbody.innerHTML = `<tr><td colspan='4' style='text-align:center; padding:30px; color:#64748b;'>Checking ${activeRoutes.length} routes... <span id='progress'>0</span>%</td></tr>`;

    let html = "";
    const totalPairs = (activeRoutes.length * (activeRoutes.length - 1)) / 2;
    uiLog(`Total pairing matrix combinations to evaluate: ${totalPairs}`);
    
    if (totalPairs === 0) {
        uiLog("Matrix aborted: Not enough comparative paired objects found for this filter boundary.");
        tbody.innerHTML = `<tr><td colspan='4' style='text-align:center; padding:30px; color:#64748b;'>No duplicates found.</td></tr>`;
        return;
    }
    
    let processedPairs = 0;

    for (let i = 0; i < activeRoutes.length; i++) {
        for (let j = i + 1; j < activeRoutes.length; j++) {
            if (!isRunning) return;
            
            processedPairs++;
            if (processedPairs % 10 === 0 || processedPairs === totalPairs) {
                const progEl = document.getElementById('progress');
                if (progEl) progEl.innerText = Math.round((processedPairs / totalPairs) * 100);
                await new Promise(r => setTimeout(r, 1));
            }

            const rA = activeRoutes[i];
            const rB = activeRoutes[j];

            if (fastDist(rA.startPoint, rB.startPoint) > 0.5) continue;
            if (rA.maxLat < rB.minLat || rA.minLat > rB.maxLat || rA.maxLon < rB.minLon || rA.minLon > rB.maxLon) continue;

            const resA = findOverlap(rA.latlngs, rB.latlngs);
            const resB = findOverlap(rB.latlngs, rA.latlngs);
            const finalPercent = Math.min(resA.percent, resB.percent);

            if (finalPercent >= threshold) {
                html += `<tr>
                    <td>${rA.name}</td>
                    <td>${rB.name}</td>
                    <td><span class='badge-value'>${finalPercent.toFixed(1)}% match</span></td>
                    <td style='text-align:center;'><button class='btn-table-action' onclick="showComparison('${rA.id}', '${rB.id}')">View Map</button></td>
                </tr>`;
            }
        }
    }
    
    uiLog(`Engine pass completed. Rendered output to dashboard screen.`);
    tbody.innerHTML = html || `<tr><td colspan='4' style='text-align:center; padding:30px; color:#64748b;'>No duplicates found above ${threshold}%.</td></tr>`;
}

let isRunning = false;
let debounceTimer;

document.getElementById('overlapSlider').oninput = function() {
    document.getElementById('sliderVal').innerText = this.value;
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        isRunning = false; 
        setTimeout(() => { runDuplicateCheck(); }, 10);
    }, 300);
};

document.getElementById('countryFilter').onchange = function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        isRunning = false;
        setTimeout(() => { runDuplicateCheck(); }, 10);
    }, 300);
};

// Auto boot execution sequence
uiLog("Triggering baseline matrix parsing engine run...");
runDuplicateCheck();

let previewMap;
function showComparison(idA, idB) {
    document.getElementById('mapModal').style.display = 'block';
    const rA = decodedRoutes.find(r => r.id === idA);
    const rB = decodedRoutes.find(r => r.id === idB);
    if (!rA || !rB) return;

    if (!previewMap) {
        previewMap = L.map('compareMap');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(previewMap);
    } else {
        previewMap.eachLayer(layer => { if (layer instanceof L.Polyline) previewMap.removeLayer(layer); });
    }

    const lineA = L.polyline(rA.latlngs, {color: '#0284c7', weight: 4, opacity: 0.6}).addTo(previewMap);
    const lineB = L.polyline(rB.latlngs, {color: '#ef4444', weight: 4, opacity: 0.6}).addTo(previewMap);

    const matchData = findOverlap(rA.latlngs, rB.latlngs);
    (matchData.segments || []).forEach(seg => { L.polyline(seg, {color: '#22c55e', weight: 6, opacity: 1}).addTo(previewMap); });
    previewMap.fitBounds(new L.featureGroup([lineA, lineB]).getBounds(), {padding: [40, 40]});
    setTimeout(() => { previewMap.invalidateSize(); }, 200);
}

function closeMap() { document.getElementById('mapModal').style.display = 'none'; }
</script>

</body>
</html>