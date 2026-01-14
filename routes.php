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
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <title>Strava Routes</title>

  <!-- Pico CSS -->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css"
  />
</head>
<body>
    
<main class="container">

  <!-- Header -->
  <header class="grid">
    <hgroup>
      <h1>Strava Routes</h1>
      <p>Your saved Strava routes</p>
    </hgroup>

    <div style="text-align:right">
      <button id="themeToggle" class="secondary outline">
        🌙 Dark mode
      </button>
      <a href="logout.php" role="button" class="contrast outline">
        Logout
      </a>
    </div>
  </header>

  <!-- Filters -->
  <section>
    <form class="grid">
      <input id="filterName" placeholder="Filter by name">

      <input id="filterMinDistance" type="number" step="0.1" placeholder="Min distance (km)">
      <input id="filterMaxDistance" type="number" step="0.1" placeholder="Max distance (km)">

      <input id="filterMinElevation" type="number" placeholder="Min elevation (m)">
      <input id="filterMaxElevation" type="number" placeholder="Max elevation (m)">

      <select id="sortField">
        <option value="name">Sort: Name</option>
        <option value="distance">Sort: Distance</option>
        <option value="elevation">Sort: Elevation</option>
      </select>

      <select id="sortOrder">
        <option value="asc">Ascending</option>
        <option value="desc">Descending</option>
      </select>
    </form>
  </section>

  <!-- Actions -->
  <section>
    <button id="fetchNewBtn">
      🔄 Fetch new routes from Strava
    </button>
  </section>

  <!-- Routes -->
<section>
  <figure>
    <table id="routesTable" class="striped hover">
      <thead>
        <tr>
        <th data-sort="name">Name</th>
        <th data-sort="distance">Distance (km)</th>
        <th data-sort="elevation">Elevation (m)</th>
        <th>Type</th>
        </tr>
      </thead>
      <tbody id="routesBody">
        <!-- Rows injected by JS -->
      </tbody>
    </table>
  </figure>
</section>


</main>

<script>
let routes = <?php echo json_encode($routes_db, JSON_UNESCAPED_UNICODE); ?>;

// Convert distance to km for filtering
routes.forEach(r => r.distance_km = r.distance / 1000);


    
function renderRoutes(data) {
  const tbody = document.getElementById("routesBody");
  tbody.innerHTML = "";

  if (data.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" style="text-align:center">
          No routes found
        </td>
      </tr>
    `;
    return;
  }

  data.forEach(route => {
    const tr = document.createElement("tr");

    tr.innerHTML = `
      <td>${route.name}</td>
      <td>${route.distance_km.toFixed(2)}</td>
      <td>${route.elevation}</td>
      <td>${route.type}</td>
    `;

    tbody.appendChild(tr);
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

document.querySelectorAll("th[data-sort]").forEach(th => {
  th.style.cursor = "pointer";

  th.addEventListener("click", () => {
    const field = th.dataset.sort;

    if (sortField === field) {
      sortOrder = sortOrder === "asc" ? "desc" : "asc";
    } else {
      sortField = field;
      sortOrder = "asc";
    }

    document.getElementById("sortField").value = field;
    document.getElementById("sortOrder").value = sortOrder;

    applyFilters();
  });
});
</script>
    
<!-- THEME TOGGLE SCRIPT -->
<script>
  const toggle = document.getElementById("themeToggle");
  const root = document.documentElement;

  // Load saved theme
  const savedTheme = localStorage.getItem("theme");
  if (savedTheme) {
    root.setAttribute("data-theme", savedTheme);
    toggle.textContent = savedTheme === "dark"
      ? "☀️ Light mode"
      : "🌙 Dark mode";
  }

  toggle.addEventListener("click", () => {
    const current = root.getAttribute("data-theme") || "light";
    const next = current === "light" ? "dark" : "light";

    root.setAttribute("data-theme", next);
    localStorage.setItem("theme", next);

    toggle.textContent = next === "dark"
      ? "☀️ Light mode"
      : "🌙 Dark mode";
  });
</script>
    
</body>
</html>
