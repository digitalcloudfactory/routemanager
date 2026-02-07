<?php
// PHP only provides encoded polylines (no geometry math here)
$encoded1 = 'kzehG`lrH~FpAhCbDxI~^rc@p_@~EG~B`IxLz@vAlGbE...';
$encoded2 = 'ezehG`lrHxFpAhCbDxI~^rc@p_@~EG~B`IxLz@vAlGbE...';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Route Overlap</title>

  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

  <!-- Mapbox polyline decoder -->
  <script src="https://unpkg.com/@mapbox/polyline"></script>

  <style>
    body { margin:0; }
    #map { width:100%; height:100vh; }
    .stats {
      position:absolute;
      top:10px;
      left:10px;
      background:white;
      padding:10px 12px;
      border-radius:6px;
      font-family:sans-serif;
      box-shadow:0 2px 8px rgba(0,0,0,.2);
      z-index:1000;
    }
  </style>
</head>
<body>

<div id="map"></div>
<div class="stats" id="stats">Computing overlap…</div>

<script>
/* =======================
   INPUT
======================= */
const encoded1 = <?= json_encode($encoded1) ?>;
const encoded2 = <?= json_encode($encoded2) ?>;

/* =======================
   MAP SETUP
======================= */
const map = L.map('map');

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19
}).addTo(map);

/* =======================
   DECODE POLYLINES
======================= */
// Mapbox polyline.decode → [lat, lon]
const route1 = polyline.decode(encoded1);
const route2 = polyline.decode(encoded2);

// Convert to Leaflet LatLng
const latlngs1 = route1.map(p => L.latLng(p[0], p[1]));
const latlngs2 = route2.map(p => L.latLng(p[0], p[1]));

/* =======================
   DRAW ROUTES
======================= */
const line1 = L.polyline(latlngs1, { color:'blue', weight:4 }).addTo(map);
const line2 = L.polyline(latlngs2, { color:'red',  weight:4 }).addTo(map);

map.fitBounds(L.featureGroup([line1, line2]).getBounds(), { padding:[20,20] });

/* =======================
   GEOMETRY HELPERS (meters)
======================= */
function projectLine(latlngs) {
  return latlngs.map(ll => map.project(ll, map.getZoom()));
}

function pointToSegmentDistance(p, a, b) {
  const dx = b.x - a.x;
  const dy = b.y - a.y;

  if (dx === 0 && dy === 0) {
    return Math.hypot(p.x - a.x, p.y - a.y);
  }

  let t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / (dx*dx + dy*dy);
  t = Math.max(0, Math.min(1, t));

  const cx = a.x + t * dx;
  const cy = a.y + t * dy;

  return Math.hypot(p.x - cx, p.y - cy);
}

function segmentDistance(a1, a2, b1, b2) {
  return Math.min(
    pointToSegmentDistance(a1, b1, b2),
    pointToSegmentDistance(a2, b1, b2),
    pointToSegmentDistance(b1, a1, a2),
    pointToSegmentDistance(b2, a1, a2)
  );
}

/* =======================
   OVERLAP MATCHING
======================= */
function findOverlap(latlngsA, latlngsB, tolerance = 12, window = 25) {
  const pA = projectLine(latlngsA);
  const pB = projectLine(latlngsB);

  let total = 0;
  let overlap = 0;
  const segments = [];

  for (let i = 0; i < pA.length - 1; i++) {
    const a1 = pA[i];
    const a2 = pA[i+1];
    const segLen = Math.hypot(a2.x - a1.x, a2.y - a1.y);
    total += segLen;

    const start = Math.max(0, i - window);
    const end   = Math.min(pB.length - 2, i + window);

    let matched = false;

    for (let j = start; j <= end; j++) {
      if (segmentDistance(a1, a2, pB[j], pB[j+1]) <= tolerance) {
        matched = true;
        break;
      }
    }

    if (matched) {
      overlap += segLen;
      segments.push([latlngsA[i], latlngsA[i+1]]);
    }
  }

  return { total, overlap, percent: (overlap/total)*100, segments };
}

/* =======================
   RUN MATCH (both ways)
======================= */
const A = findOverlap(latlngs1, latlngs2);
const B = findOverlap(latlngs2, latlngs1);

// conservative result
const overlapMeters  = Math.min(A.overlap, B.overlap);
const overlapPercent = Math.min(A.percent, B.percent);

/* =======================
   DRAW OVERLAP
======================= */
A.segments.forEach(seg => {
  L.polyline(seg, { color:'lime', weight:7 }).addTo(map);
});

/* =======================
   UI STATS
======================= */
document.getElementById('stats').innerHTML = `
<b>Overlap</b><br>
Distance: ${(overlapMeters/1000).toFixed(2)} km<br>
Percent: ${overlapPercent.toFixed(1)} %
`;
</script>

</body>
</html>
