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

        // Decode polylines in memory
        console.groupCollapsed('🔍 Polyline Decoding Engine');
        let skippedCount = 0;

        decodedRoutes = rawRoutesData.map((r) => {
            if (!r.summary_polyline || r.summary_polyline.length < 10) {
                skippedCount++;
                return null;
            }
            try {
                const points = polyline.decode(r.summary_polyline);
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
                    name: r.name,
                    country: r.country,
                    id: r.route_id,
                    coords: coords,
                    minLat, maxLat, minLon, maxLon
                };
            } catch (e) { 
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
    const threshold = parseInt(document.getElementById('overlapSlider').value, 10);
    const selectedCountry = document.getElementById('countryFilter').value;
    const tbody = document.getElementById('resultsBody');
    
    logStage('3. SCAN STARTED', `Analyzing ${decodedRoutes.length} routes. Min Overlap: ${threshold}%, Country: "${selectedCountry}" (Exec ID: #${executionId})`);

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