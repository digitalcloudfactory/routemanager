<?php
session_start();

/* ===============================
   AUTH CHECK
================================ */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

/* ===============================
   DATABASE CONFIG
================================ */

$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'routes';
$db_user = '68a00bc6768780007ea0fea26ffa';
$db_pass = '069668a0-0bc6-788a-8000-597667343eee';

/* ===============================
   DB CONNECTION
================================ */

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
   LOAD ROUTES (PER USER)
================================ */

$stmt = $pdo->prepare("
    SELECT route_id, name, distance_km, elevation, type
    FROM strava_routes
    WHERE user_id = ?
    ORDER BY updated_at DESC
");

$stmt->execute([$user_id]);
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html data-theme="light">
<head>
  <meta charset="UTF-8">
  <title>My Strava Routes</title>

  <!-- Pico CSS -->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css"
  />

  <!-- Optional custom overrides -->
  <link rel="stylesheet" href="style.css">
</head>

<body>
<main class="container">

  <!-- HEADER -->
  <header class="grid">
    <div>
      <h1>My Strava Routes</h1>
    </div>
    <div style="text-align:right">
      <button id="themeToggle" class="secondary">🌙 Dark</button>
      <a href="logout.php" role="button" class="secondary">Logout</a>
    </div>
  </header>

  <!-- CONTROLS -->
  <section>
    <form class="grid">
      <input id="filterName" placeholder="Filter by name">
      <input id="filterDistance" type="number" placeholder="Min distance (km)">
      <input id="filterElevation" type="number" placeholder="Min elevation (m)">
      <button type="button" id="fetchRoutes">Fetch new routes</button>
    </form>
  </section>

  <!-- TABLE -->
  <section>
    <figure style="overflow-x:auto">
      <table class="striped hover" id="routesTable">
        <thead>
          <tr>
            <th data-sort="name">Name</th>
            <th data-sort="distance_km">Distance (km)</th>
            <th data-sort="elevation">Elevation (m)</th>
            <th>Type</th>
          </tr>
        </thead>
        <tbody id="routesBody"></tbody>
      </table>
    </figure>
  </section>

</main>

<script>
/* ===============================
   DATA FROM PHP
================================ */

const routes = <?php echo json_encode($routes, JSON_UNESCAPED_UNICODE); ?>;
let filteredRoutes = [...routes];
let sortField = null;
let sortOrder = 'asc';

/* ===============================
   RENDER TABLE
================================ */

function renderRoutes(data) {
  const tbody = document.getElementById('routesBody');
  tbody.innerHTML = '';

  if (data.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="4" style="text-align:center">
          No routes found
        </td>
      </tr>`;
    return;
  }

  data.forEach(r => {
    tbody.innerHTML += `
      <tr>
        <td>${r.name}</td>
        <td>${Number(r.distance_km).toFixed(2)}</td>
        <td>${r.elevation}</td>
        <td>${r.type}</td>
      </tr>`;
  });
}

/* ===============================
   FILTER & SORT
================================ */

function applyFilters() {
  const name = filterName.value.toLowerCase();
  const minDist = parseFloat(filterDistance.value) || 0;
  const minElev = parseFloat(filterElevation.value) || 0;

  filteredRoutes = routes.filter(r =>
    r.name.toLowerCase().includes(name) &&
    r.distance_km >= minDist &&
    r.elevation >= minElev
  );

  if (sortField) {
    filteredRoutes.sort((a, b) => {
      const v1 = a[sortField];
      const v2 = b[sortField];
      return sortOrder === 'asc'
        ? v1 > v2 ? 1 : -1
        : v1 < v2 ? 1 : -1;
    });
  }

  renderRoutes(filteredRoutes);
}

/* ===============================
   SORT HANDLERS
================================ */

document.querySelectorAll('th[data-sort]').forEach(th => {
  th.style.cursor = 'pointer';
  th.addEventListener('click', () => {
    const field = th.dataset.sort;
    sortOrder = sortField === field && sortOrder === 'asc' ? 'desc' : 'asc';
    sortField = field;
    applyFilters();
  });
});

/* ===============================
   FETCH NEW ROUTES (AJAX)
================================ */

document.getElementById('fetchRoutes').onclick = async () => {
  const btn = fetchRoutes;
  btn.setAttribute('aria-busy', 'true');

  await fetch('fetch_routes.php');
  location.reload();
};

/* ===============================
   THEME TOGGLE
================================ */

const html = document.documentElement;
const toggle = document.getElementById('themeToggle');

const savedTheme = localStorage.getItem('theme') || 'light';
html.setAttribute('data-theme', savedTheme);
toggle.textContent = savedTheme === 'dark' ? '☀️ Light' : '🌙 Dark';

toggle.onclick = () => {
  const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
  html.setAttribute('data-theme', newTheme);
  localStorage.setItem('theme', newTheme);
  toggle.textContent = newTheme === 'dark' ? '☀️ Light' : '🌙 Dark';
};

/* ===============================
   INIT
================================ */

filterName.oninput =
filterDistance.oninput =
filterElevation.oninput = applyFilters;

renderRoutes(routes);
</script>

</body>
</html>
