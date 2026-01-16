<?php
session_start();
if (!isset($_SESSION['internal_user_id'])) {
    header("Location: index.php");
    exit;
}
$internalUserId = $_SESSION['internal_user_id'];

// same DB + routes + tags loading as routes.php
// produce $routes array
?>

<?php include 'header.php'; ?>

<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

<style>
#map {
  height: calc(100vh - 120px);
  border-radius: 12px;
}
</style>

<main class="container">
  <header class="grid">
    <strong>Route Map</strong>
    <button id="openFilters" class="secondary">Filters</button>
  </header>

  <div id="map"></div>

  <?php include 'filter_panel.php'; ?>
</main>

<?php include 'footer.php'; ?>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>

<script>
const routes = <?= json_encode($routes, JSON_UNESCAPED_UNICODE); ?>;

const map = L.map('map');
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap'
}).addTo(map);

let routeLayers = [];

function clearRoutes() {
  routeLayers.forEach(l => map.removeLayer(l));
  routeLayers = [];
}

function drawRoutes(data) {
  clearRoutes();

  data.forEach(route => {
    if (!route.summary_polyline) return;

    const coords = polyline.decode(route.summary_polyline)
      .map(c => [c[0], c[1]]);

    const line = L.polyline(coords, {
      weight: 3,
      opacity: 0.8
    }).addTo(map);

    line.bindPopup(`
      <strong>${route.name}</strong><br>
      ${Number(route.distance_km).toFixed(1)} km<br>
      ${route.tags || ''}
    `);

    routeLayers.push(line);
  });

  if (routeLayers.length) {
    const group = L.featureGroup(routeLayers);
    map.fitBounds(group.getBounds(), { padding: [20, 20] });
  }
}

function onFiltersUpdated(data) {
  drawRoutes(data);
}

// initial render
drawRoutes(routes);
</script>

<script src="routes_shared.js"></script>
