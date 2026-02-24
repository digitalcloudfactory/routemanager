<?php
/* ===============================
----Important rule going forward----
-Use case-	            -ID to use-
DB queries	            internal_user_id ✅
Strava API calls	    strava_id
Session auth check	    internal_user_id
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

// 1. Fetch unique countries
$countryStmt = $pdo->prepare("SELECT DISTINCT country FROM strava_routes WHERE user_id = ? AND country IS NOT NULL AND country != '' ORDER BY country ASC");
$countryStmt->execute([$internalUserId]);
$countries = $countryStmt->fetchAll(PDO::FETCH_COLUMN);

// 2. Fetch all routes (Make sure 'country' is in the SELECT)
$stmt = $pdo->prepare("SELECT route_id, name, summary_polyline, distance_km, country FROM strava_routes WHERE user_id = ?");
$stmt->execute([$internalUserId]);
$allRoutes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// DEBUG: Uncomment the line below to see if PHP actually found countries
 print_r($countries);

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>Route Duplicate Finder</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>
</head>
<body>

<div class="stats" style="margin-bottom: 20px; font-family: sans-serif;">
    <b>Duplicate Finder</b><br>
    Min Match: <input type="range" id="overlapSlider" min="10" max="100" value="80"> 
    <span id="sliderVal">80</span>% | 

Country: 
<select id="countryFilter">
    <option value="all">All Countries (<?= count($allRoutes) ?> routes)</option>
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

<table id="duplicateTable" border="1" style="width:100%; border-collapse: collapse; font-family: sans-serif;">
    <thead>
        <tr style="background: #f4f4f4;">
            <th style="padding: 10px;">Route A</th>
            <th style="padding: 10px;">Route B</th>
            <th style="padding: 10px;">Overlap %</th>
        </tr>
    </thead>
    <tbody id="resultsBody"></tbody>
</table>

<div id="mapModal" onclick="closeMap()" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8);">
    
    <div onclick="event.stopPropagation()" style="background:white; margin:5% auto; padding:20px; width:80%; height:80%; border-radius:10px; position:relative;">
        
        <span onclick="closeMap()" style="position:absolute; right:20px; top:10px; cursor:pointer; font-size:30px; font-family:Arial;">&times;</span>
        
        <div id="compareMap" style="width:100%; height:100%;"></div>
    </div>
</div>
<script>
// 1. Data from PHP
const allRoutesData = <?= json_encode($allRoutes ?? []) ?>;


    

// 1. Pre-process and categorize
const decodedRoutes = allRoutesData.map(r => {
    if (!r.summary_polyline || r.summary_polyline.length < 10) return null;
    try {
        const points = polyline.decode(r.summary_polyline);
        return {
            name: r.name,
            country: r.country,
            id: r.route_id,
            latlngs: points.map(p => L.latLng(p[0], p[1])),
            startPoint: [points[0][0], points[0][1]] // [lat, lon]
        };
    } catch (e) { return null; }
}).filter(r => r !== null);

// 2. Simple distance check for the "Guard"
function fastDist(p1, p2) {
    const dy = p1[0] - p2[0];
    const dx = p1[1] - p2[1];
    return Math.sqrt(dx*dx + dy*dy); // Simple Euclidean for rough filtering
}

// --- YOUR ORIGINAL FUNCTIONS (UNTOUCHED) ---
// Compute Haversine distance between two [lat, lon] points
function haversineDistance(p1, p2) {
  const R = 6371000; // meters
  const toRad = Math.PI / 180;
  const dLat = (p2[0] - p1[0]) * toRad;
  const dLon = (p2[1] - p1[1]) * toRad;
  const lat1 = p1[0] * toRad;
  const lat2 = p2[0] * toRad;

  const a = Math.sin(dLat/2)**2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon/2)**2;
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}

// Distance from point p to segment a-b in lat/lon meters
function pointToSegmentDistanceMeters(p, a, b) {
  const dx = b[1] - a[1]; // lon difference
  const dy = b[0] - a[0]; // lat difference

  if (dx === 0 && dy === 0) return haversineDistance(p, a);

  let t = ((p[1]-a[1])*dx + (p[0]-a[0])*dy)/(dx*dx + dy*dy);
  t = Math.max(0, Math.min(1, t));

  const proj = [a[0] + t*dy, a[1] + t*dx];
  return haversineDistance(p, proj);
}

// Distance between two segments in meters
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
    
    // 1. Point Sampling: increase 'i += 1' to 'i += 4' for 4x speed
    const step = 2; 

    for (let i = 0; i < latlngsA.length - 1; i += step) {
        const a1 = latlngsA[i];
        const a2 = latlngsA[i+1] || latlngsA[i];
        const segLen = haversineDistance([a1.lat, a1.lng], [a2.lat, a2.lng]);
        total += segLen;

        let matched = false;
        
        // 2. Proximity Filter: Only check segments in B that are roughly nearby
        for (let j = 0; j < latlngsB.length - 1; j += step) {
            const b1 = latlngsB[j];
            
            // QUICK BOX CHECK: If the points are more than ~200m apart, 
            // don't do the heavy segment math. (0.002 degrees is ~220m)
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
            segments.push([a1, a2]); // Store for the map
        }
    }

    return { 
        percent: total > 0 ? (overlap / total) * 100 : 0, 
        segments: segments // This must match the key used in showComparison
    };
}
// --- END ORIGINAL FUNCTIONS ---

async function runDuplicateCheck() {
    isRunning = true;
    const threshold = parseInt(document.getElementById('overlapSlider').value);
    const selectedCountry = document.getElementById('countryFilter').value; // Get selected country
    const tbody = document.getElementById('resultsBody');
    
    // STEP 1: Filter the routes list before starting the loop
    const activeRoutes = decodedRoutes.filter(r => {
        if (selectedCountry === "all") return true;
        return r.country === selectedCountry;
    });
    
    
    tbody.innerHTML = `<tr><td colspan='4' style='text-align:center; padding:20px;'>Checking ${activeRoutes.length} routes... <span id='progress'>0</span>%</td></tr>`;

    let html = "";
    const totalPairs = (activeRoutes.length * (activeRoutes.length - 1)) / 2;
    let processedPairs = 0;

    // STEP 2: Use 'activeRoutes' instead of 'decodedRoutes'
    for (let i = 0; i < activeRoutes.length; i++) {
        for (let j = i + 1; j < activeRoutes.length; j++) {
            if (!isRunning) return;
            
            processedPairs++;
            if (processedPairs % 10 === 0) {
                document.getElementById('progress').innerText = Math.round((processedPairs / totalPairs) * 100);
                await new Promise(r => setTimeout(r, 1));
            }

            const rA = activeRoutes[i];
            const rB = activeRoutes[j];

            // 1. DISTANCE GUARD (Keep this! It saves massive CPU)
            if (fastDist(rA.startPoint, rB.startPoint) > 0.5) continue;

            // If the "box" of Route A doesn't even touch the "box" of Route B, skip.
            if (rA.maxLat < rB.minLat || rA.minLat > rB.maxLat || 
                rA.maxLon < rB.minLon || rA.minLon > rB.maxLon) {
                continue;
            }

            // 2. THE CALCULATION
            const resA = findOverlap(rA.latlngs, rB.latlngs);
            const resB = findOverlap(rB.latlngs, rA.latlngs);
            const finalPercent = Math.min(resA.percent, resB.percent);

            if (finalPercent >= threshold) {
            html += `<tr>
                <td style="padding:10px;">${rA.name}</td>
                <td style="padding:10px;">${rB.name}</td>
                <td style="padding:10px;"><strong>${finalPercent.toFixed(1)}%</strong></td>
                <td style="padding:10px;">
                    <button onclick="showComparison(${i}, ${j})">View Map</button>
                </td>
            </tr>`;
            }
        }
    }
    
    tbody.innerHTML = html || `<tr><td colspan='3' style='text-align:center; padding:20px;'>No duplicates found above ${threshold}%.</td></tr>`;
}

// 4. Slider Listener
let isRunning = false; // Flag to check if a process is active
let debounceTimer;

document.getElementById('overlapSlider').oninput = function() {
    const val = this.value;
    document.getElementById('sliderVal').innerText = val;

    // 1. Clear the timer every time the slider moves
    clearTimeout(debounceTimer);

    // 2. Set a new timer
    debounceTimer = setTimeout(() => {
        // 3. Stop any currently running comparison
        isRunning = false; 
        
        // 4. Start the new comparison after a small delay
        setTimeout(() => {
            runDuplicateCheck();
        }, 10);
    }, 300); // 300ms delay
};

let previewMap;

function showComparison(indexA, indexB) {
    document.getElementById('mapModal').style.display = 'block';
    const rA = decodedRoutes[indexA];
    const rB = decodedRoutes[indexB];

    if (!previewMap) {
        previewMap = L.map('compareMap');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(previewMap);
    } else {
        previewMap.eachLayer(layer => {
            if (layer instanceof L.Polyline) previewMap.removeLayer(layer);
        });
    }

    const lineA = L.polyline(rA.latlngs, {color: 'blue', weight: 3, opacity: 0.5}).addTo(previewMap);
    const lineB = L.polyline(rB.latlngs, {color: 'red', weight: 3, opacity: 0.5}).addTo(previewMap);

    // FIX: Run the overlap check and safely access segments
    const matchData = findOverlap(rA.latlngs, rB.latlngs);
    
    // The "|| []" ensures that if segments is undefined, the code doesn't crash
    const overlapSegments = matchData.segments || []; 
    
    overlapSegments.forEach(seg => {
        L.polyline(seg, {color: '#32CD32', weight: 6, opacity: 1}).addTo(previewMap);
    });

    const group = new L.featureGroup([lineA, lineB]);
    previewMap.fitBounds(group.getBounds(), {padding: [20, 20]});
}

function closeMap() {
    // Hide the modal
    document.getElementById('mapModal').style.display = 'none';
    
    // Optional: Stop any background processing or clear map if needed
    console.log("Map closed.");
}

// Add an event listener for the country dropdown
document.getElementById('countryFilter').onchange = function() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        isRunning = false;
        setTimeout(() => { runDuplicateCheck(); }, 10);
    }, 300);
};

</script>
