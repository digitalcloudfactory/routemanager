<?php
session_start();

ini_set('memory_limit', '512M'); 
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<script src="routes_shared.js"></script>

<style>
body {
  font-family: 'Inter', sans-serif;
  background-color: #f4f6f9;
  color: #1e293b;
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

/* Float header dashboard cleanly over Light Map Layer Canvas */
header.grid {
  position: absolute !important;
  top: 20px;
  left: 20px;
  right: 20px;
  z-index: 1000 !important;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(8px);
  padding: 1rem 1.5rem !important;
  border-radius: 12px;
  border: 1px solid #e2e8f0;
  box-shadow: 0 4px 20px rgba(148, 163, 184, 0.12);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.user-profile-block {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-profile-block img {
  border: 2px solid #0284c7;
  padding: 2px;
  background: #ffffff;
}

.user-profile-block strong {
  color: #0f172a;
  font-size: 0.95rem;
}

.user-profile-block small {
  color: #64748b;
  font-size: 0.8rem;
}

.actions-block {
  display: flex;
  gap: 8px;
  align-items: center;
}

/* Custom Uniform Control Button Framework Elements */
.btn-custom {
  font-family: 'Inter', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  border: 1px solid #cbd5e1;
  background: #ffffff;
  color: #475569;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-custom:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #94a3b8;
}

.btn-custom.primary {
  background: #0284c7;
  color: #ffffff;
  border: none;
}

.btn-custom.primary:hover {
  background: #0369a1;
  color: #ffffff;
}

.range-slider input[type="range"]::-webkit-slider-thumb { pointer-events: auto; cursor: pointer; }
.range-slider input[type="range"]::-moz-range-thumb { pointer-events: auto; cursor: pointer; }
#filterDistanceMin { background: transparent !important; }       
</style>

<main class="container">
<header class="grid">
  <div class="user-profile-block">
    <img src="<?= htmlspecialchars($user['avatar'] ?? '') ?>" alt="Avatar" width="48" height="48" style="border-radius:50%">
    <div>
      <strong><?= htmlspecialchars(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')) ?></strong><br>
      <small>Last Sync: <?= !empty($user['last_routes_sync']) ? htmlspecialchars($user['last_routes_sync']) : 'Never' ?></small>
    </div>
  </div>

  <div class="actions-block">
    <a id="mapLink" href="routes.php" class="btn-custom" style="text-decoration: none;">Table view</a>
    <button id="fetchRoutes" class="btn-custom primary" type="button">Sync Strava</button>
    <button id="openFilters" class="btn-custom" type="button">Filters ⚙️</button>
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

const map = L.map('map', { trackResize: true }).setView([50.8503, 4.3517], 2);

// Swapped out to unified Light Mode Variant Positron Style Map Layer Trace
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
                
                // Traces applied in crisp thematic Canyon Blue
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

                line.bindPopup(`
                    <div style="font-family: 'Inter', sans-serif; font-size: 13px; line-height: 1.5; color: #1e293b; padding: 2px;">
                        <strong style="font-size: 14px; color: #0f172a; display: block; margin-bottom: 2px;">${route.name}</strong>
                        <span style="color: #94a3b8; font-size: 11px;">📅 ${dateCreated}</span>
                        <hr style="margin: 8px 0; border: 0; border-top: 1px solid #e2e8f0;">
                        <strong>📏 Distance:</strong> ${distance} km<br>
                        <strong>⛰️ Elevation:</strong> ${elevation} m<br>
                        ${tagsHTML}
                    </div>
                `);

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
</script>

<?php include 'footer.php'; ?>
<?php exit(0); ?>
