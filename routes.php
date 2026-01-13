<?php
session_start();

// Database credentials
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'routes';
$db_user = '68a00bc6768780007ea0fea26ffa';
$db_pass = '069668a0-0bc6-788a-8000-597667343eee';

// Strava access token check
if (!isset($_SESSION['access_token'])) {
    header("Location: index.php");
    exit;
}

// Connect to DB with utf8mb4
try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch routes from DB
$stmt = $pdo->query("SELECT * FROM strava_routes");
$routes_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Strava Routes Dashboard</title>
<link rel="stylesheet" href="style.css">
<style>
.filter-sort {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
}
.filter-sort label, .filter-sort select, .filter-sort input {
    display: block;
}
</style>
</head>
<body>
<div class="container">
<header>
    <h1>Strava Routes</h1>
    <a class="logout-button" href="logout.php">Logout</a>
</header>

<!-- Filters + Sorting -->
<div class="filter-sort">
    <label>
        Name:
        <input type="text" id="filterName" placeholder="Search by name">
    </label>
    <label>
        Min Distance (km):
        <input type="number" id="filterMinDistance" step="0.1">
    </label>
    <label>
        Max Distance (km):
        <input type="number" id="filterMaxDistance" step="0.1">
    </label>
    <label>
        Min Elevation (m):
        <input type="number" id="filterMinElevation">
    </label>
    <label>
        Max Elevation (m):
        <input type="number" id="filterMaxElevation">
    </label>
    <label>
        Sort by:
        <select id="sortField">
            <option value="name">Name</option>
            <option value="distance">Distance</option>
            <option value="elevation">Elevation</option>
        </select>
    </label>
    <label>
        Order:
        <select id="sortOrder">
            <option value="asc">Ascending</option>
            <option value="desc">Descending</option>
        </select>
    </label>
</div>

<!-- Fetch New Routes Button -->
<button id="fetchNewBtn">Fetch New Routes from Strava</button>

<!-- Routes Grid -->
<div class="routes-grid" id="routesGrid">
    <!-- Routes will be injected here by JS -->
</div>
</div>

<script>
let routes = <?php echo json_encode($routes_db, JSON_UNESCAPED_UNICODE); ?>;

// Convert distance to km for filtering
routes.forEach(r => r.distance_km = r.distance / 1000);

function renderRoutes(data) {
    const container = document.getElementById('routesGrid');
    container.innerHTML = '';
    if (data.length === 0) {
        container.innerHTML = "<p class='no-routes'>No routes match your filters.</p>";
        return;
    }
    data.forEach(route => {
        const card = document.createElement('div');
        card.className = 'route-card';
        card.innerHTML = `
            <h3>${route.name}</h3>
            <div class='route-details'>
                <p>Distance: ${route.distance_km.toFixed(2)} km</p>
                <p>Elevation Gain: ${route.elevation} m</p>
                <p>Type: ${route.type}</p>
            </div>
        `;
        container.appendChild(card);
    });
}

// Filtering & Sorting
function applyFilters() {
    let filtered = routes.slice();

    const name = document.getElementById('filterName').value.toLowerCase();
    const minD = parseFloat(document.getElementById('filterMinDistance').value);
    const maxD = parseFloat(document.getElementById('filterMaxDistance').value);
    const minE = parseFloat(document.getElementById('filterMinElevation').value);
    const maxE = parseFloat(document.getElementById('filterMaxElevation').value);

    filtered = filtered.filter(r => {
        if (name && !r.name.toLowerCase().includes(name)) return false;
        if (!isNaN(minD) && r.distance_km < minD) return false;
        if (!isNaN(maxD) && r.distance_km > maxD) return false;
        if (!isNaN(minE) && r.elevation < minE) return false;
        if (!isNaN(maxE) && r.elevation > maxE) return false;
        return true;
    });

    const sortField = document.getElementById('sortField').value;
    const sortOrder = document.getElementById('sortOrder').value;

    filtered.sort((a, b) => {
        let valA = a[sortField];
        let valB = b[sortField];
        if (sortField === 'distance') valA = a.distance_km; valB = b.distance_km;
        if (typeof valA === 'string') valA = valA.toLowerCase();
        if (typeof valB === 'string') valB = valB.toLowerCase();
        if (valA < valB) return sortOrder === 'asc' ? -1 : 1;
        if (valA > valB) return sortOrder === 'asc' ? 1 : -1;
        return 0;
    });

    renderRoutes(filtered);
}

// Attach event listeners
['filterName','filterMinDistance','filterMaxDistance','filterMinElevation','filterMaxElevation','sortField','sortOrder']
.forEach(id => {
    document.getElementById(id).addEventListener('input', applyFilters);
});

renderRoutes(routes); // initial render

// Fetch new routes from Strava without reload
document.getElementById('fetchNewBtn').addEventListener('click', () => {
    fetch('fetch_routes.php', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            routes = data.routes.map(r => ({ ...r, distance_km: r.distance/1000 }));
            applyFilters();
            alert('New routes fetched!');
        } else {
            alert('Error fetching routes.');
        }
    });
});
</script>
</body>
</html>
