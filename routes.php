<?php
session_start();

// Configuration
define('STRAVA_CLIENT_ID', '6839');
define('STRAVA_CLIENT_SECRET', '1a1057defe991fd6c2711f1199a3563cb3d5395f');
define('STRAVA_REDIRECT_URI', 'http://strava-routes.wuaze.com/callback.php');

define('STRAVA_AUTH_URL', 'https://www.strava.com/oauth/authorize');
define('STRAVA_TOKEN_URL', 'https://www.strava.com/oauth/token');
define('STRAVA_API_URL', 'https://www.strava.com/api/v3');

// Database connection
$pdo = new PDO('mysql:host=sql309.infinityfree.com;dbname=if0_38293933_routes', 'if0_38293933', 'K9OOA6jspNL8dd5');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


if (!isset($_SESSION['access_token'])) {
    // User is not authenticated → Redirect to login
    header('Location: '.STRAVA_AUTH_URL.'?client_id='.STRAVA_CLIENT_ID.'&response_type=code&redirect_uri='.urlencode(STRAVA_REDIRECT_URI).'&approval_prompt=force&scope=read,read_all');
    exit;
}

if (!isset($_SESSION['athlete_id'])) {
    // Re-fetch athlete ID if missing
    $_SESSION['athlete_id'] = getAthleteId($_SESSION['access_token']);

    if (!$_SESSION['athlete_id']) {
        die("Error: Unable to retrieve athlete ID. Please log in again.");
    }
}


// If user is not authenticated, redirect to Strava OAuth
if (!isset($_SESSION['access_token'])) {
    header('Location: '.STRAVA_AUTH_URL.'?client_id='.STRAVA_CLIENT_ID.'&response_type=code&redirect_uri='.urlencode(STRAVA_REDIRECT_URI).'&approval_prompt=force&scope=read,read_all');
    exit;
}

// Fetch athlete ID to get routes
function getAthleteId($access_token) {
    $ch = curl_init(STRAVA_API_URL.'/athlete?access_token='.$access_token);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $athlete = json_decode($response, true);
    return $athlete['id'] ?? null;
}

// Ensure athlete_id is set
if (!isset($_SESSION['athlete_id'])) {
    $_SESSION['athlete_id'] = getAthleteId($_SESSION['access_token']);
}





// Fetch user's routes from Strava API if requested
//if (isset($_POST['fetch_routes'])) {
if (isset($_GET['syncStravaRoutes'])) {
    $athlete_id = $_SESSION['athlete_id'];

    if ($athlete_id) {
        $ch = curl_init(STRAVA_API_URL."/athletes/$athlete_id/routes?per_page=200");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $_SESSION['access_token'],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $routes = json_decode($response, true);

        if (!is_array($routes)) {
            die("Error: Invalid response from Strava API");
        }

        foreach ($routes as $route) {
            if (!isset($route['id']) || !isset($route['name'])) continue;
            try {
                $stmt = $pdo->prepare("INSERT INTO routes (route_id, user_id, name, polyline, distance, elevation_gain, type, sub_type) 
                                    VALUES (:route_id, :user_id, :name, :polyline, :distance, :elevation_gain, :type, :sub_type) 
                                    ON DUPLICATE KEY UPDATE name=:name, polyline=:polyline, distance=:distance, elevation_gain=:elevation_gain, type=:type, sub_type=:sub_type");
                $stmt->execute([
                    'route_id' => $route['id'],
                    'user_id' => $athlete_id,
                    'name' => $route['name'],
                    'polyline' => $route['map']['summary_polyline'] ?? null,
                    'distance' => round(($route['distance'] ?? 0) / 1000, 1),
                    'elevation_gain' => round($route['elevation_gain'] ?? 0, 1),
                    'type' => $route['type'],
                    'sub_type' => $route['sub_type']
                ]);
            } catch (PDOException $e) {
                error_log('Database Error: ' . $e->getMessage()); // Log error
            }
        }
    } else {
        die("Error: Athlete ID not found");
    }
}


// Handle tag assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['route_id'], $_POST['tags'])) {
    $route_id = $_POST['route_id'];
    $tags = explode(',', $_POST['tags']);
    
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if (!empty($tag)) {
        $athlete_id = $_SESSION['athlete_id']; // Current user's ID
        $stmt = $pdo->prepare("INSERT INTO route_tags (route_id, user_id, tag) 
                            VALUES (:route_id, :user_id, :tag) 
                            ON DUPLICATE KEY UPDATE tag=tag");
        $stmt->execute(['route_id' => $route_id, 'user_id' => $athlete_id, 'tag' => $tag]);

        }
    }
}

// Get the current athlete's ID
$athlete_id = $_SESSION['athlete_id'];

// Fetch unique tags assigned to the routes of the current athlete
$tagsQuery = $pdo->prepare("
    SELECT DISTINCT rt.tag 
    FROM route_tags rt
    JOIN routes r ON rt.route_id = r.route_id
    WHERE r.user_id = ? 
    ORDER BY rt.tag ASC
");
$tagsQuery->execute([$athlete_id]);
$allTags = $tagsQuery->fetchAll(PDO::FETCH_COLUMN);

// Get selected tags from GET request (if any)
$selectedTags = isset($_GET['tags']) ? (array) $_GET['tags'] : []; 



// Fetch stored routes with optional tag filtering
$tag_filter = isset($_GET['tag']) ? $_GET['tag'] : '';
$athlete_id = $_SESSION['athlete_id'];

$query = "SELECT DISTINCT r.* FROM routes r LEFT JOIN route_tags rt ON r.route_id = rt.route_id WHERE r.user_id = ?";  // Ensure only the logged-in user's routes are fetched

$params = [$athlete_id];  // Securely bind the current athlete_id



// If user has selected tags for filtering
if (!empty($selectedTags)) {
    // Generate placeholders for selected tags (e.g., ?, ?, ? for multiple tags)
    $placeholders = implode(',', array_fill(0, count($selectedTags), '?'));
    $query .= " AND rt.tag IN ($placeholders)";
    $params = array_merge($params, $selectedTags);  // Add selected tags to query parameters
}

// Prepare and execute query securely
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$stored_routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Strava Routes</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #dbd7d6;
            padding: 15px;
            color: dark-gray;
        }
        .nav-links {
            display: none;
            list-style: none;
            flex-direction: column;
            position: absolute;
            top: 60px;
            left: 0;
            background-color: #dbd7d6;
            width: 200px;
            color: dark-gray;
        }
        .nav-links.active {
            display: flex;
        }
        .nav-links li {
            text-align: left;
            padding: 10px;
        }
        .nav-links a {
            color: #3c3f42;
            text-decoration: none;
        }
        .hamburger {
            font-size: 24px;
            cursor: pointer;
        }   
        .logo {
    margin-left: auto;
    color:#3c3f42;
    }

    .margin_left {
  padding: 10px;
  border: 8px solid #ccc
    }

    #distanceSlider {
    width: 50%;
    margin: 20px auto;
}


    #map {
            height: 500px;
            margin-top: 20px;
            border: 1px solid #ccc;
        }
        select, input, button {
            margin: 10px 0;
            padding: 8px;
            width: 100%;
        }
    </style>

    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />

    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/@mapbox/polyline"></script>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://unpkg.com/axios/dist/axios.min.js"></script>

<!-- Include noUiSlider CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/nouislider@15.6.0/dist/nouislider.min.css">

<!-- Include noUiSlider JS -->
<script src="https://cdn.jsdelivr.net/npm/nouislider@15.6.0/dist/nouislider.min.js"></script>

</head>
<body>
    <nav class="navbar">
        <div class="hamburger" onclick="toggleMenu()">&#9776;</div>
        <div class="logo">Strava Route Tagger Tool</div>
        <ul class="menu nav-links">
            <li><a href="#">Home</a></li>
            <li><a href="routes.php">Overview</a></li>
            <li><a href="tag_routes.php">Tag Routes</a></li>
            <li><a href="?syncStravaRoutes=true"">Sync Strava</a></li>
        </ul>
    </nav>
    <script>
        function toggleMenu() {
            document.querySelector('.nav-links').classList.toggle('active');
        }
    </script>

 <!-- jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>


<div class="margin_left" ">
<form method="GET">
    <label for="tags">Filter by Tags:</label>
    <select id="tags" name="tags[]" multiple style="width: 400px;height: 100px" class="js-example-basic-multiple">
        <?php foreach ($allTags as $tag): ?>
            <option value="<?php echo htmlspecialchars($tag); ?>" 
                <?php echo in_array($tag, $selectedTags) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($tag); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" style="width: 100px;height: 40px">Filter</button>
    
</form>





            <label>Find Address:</label>
            <input type="text" id="address_input" placeholder="Enter address" style="width: 250px;height: 40px">
            <button style="width: 40px;height: 40px" onclick="addMarker()">Go:</button>

</div>
        

<!-- Slider Container -->
<div id="distanceSlider" style="width: 80%; margin: 20px auto;"></div>
    <span>Min: <span id="minDistanceLabel"></span> km - Max: <span id="maxDistanceLabel"></span> km</span>
</div>
<!-- Elevation Gain Slider -->
<div>
    <label>Elevation Gain Filter (m):</label>
    <div id="elevationSlider" style="width: 80%; margin: 20px auto;"></div>
    <span>Min: <span id="minElevationLabel"></span> m - Max: <span id="maxElevationLabel"></span> m</span>
</div>


    <div id="map" style="height: 720px;"></div>
    
 
    <script>

            $(document).ready(function() {
            $('.select2').select2();
        });

        var map = L.map('map').setView([51.505, -0.09], 2);  // Example center point, adjust as needed
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);


        function addMarker() {
            var address = document.getElementById('address_input').value;
            if (!address) return;
            
            axios.get(`https://nominatim.openstreetmap.org/search?format=json&q=${address}`)
                .then(response => {
                    if (response.data.length > 0) {
                        var location = response.data[0];
                        var latlng = [parseFloat(location.lat), parseFloat(location.lon)];
                        L.marker(latlng).addTo(map).bindPopup(address).openPopup();
                        map.setView(latlng, 13);
                    } else {
                        alert('Address not found');
                    }
                })
                .catch(error => console.error('Error fetching location:', error));
        }

        document.querySelectorAll("tbody tr").forEach(row => {
            row.addEventListener("click", function() {
                var polyline = this.getAttribute("data-polyline");
                var name = this.getAttribute("data-name");
                
                if (polyline) {
                    var latlngs = L.Polyline.fromEncoded(polyline).getLatLngs();
                    var routeLine = L.polyline(latlngs, {color: 'blue'}).addTo(map);
                    map.fitBounds(routeLine.getBounds());
                    routeLine.bindPopup(name).openPopup();
                }
            });
        });




var colors = ["red", "blue", "green", "purple", "orange", "brown", "cyan", "magenta", "lime", "pink"];
var colorIndex = 0;  // To cycle through colors
var routeLayers = []; 

 <?php
$maxDistance = 0;
$maxElevation = 0;
foreach ($stored_routes as $route) {
    $distance_clear = json_encode(round($route['distance'], 1)); // Ensure correct formatting
    $elevation_clear = json_encode(round($route['elevation_gain'], 1)); // Ensure correct formatting

    if (!empty($route['distance']) && $route['distance'] > $maxDistance) {
        $maxDistance = $route['distance'];
    }
    if (!empty($route['elevation_gain']) && $route['elevation_gain'] > $maxElevation) {
        $maxElevation = $route['elevation_gain'];
    }

}
?>
console.log("Elevation_Gain:", <?php echo $maxElevation; ?>);

<?php foreach ($stored_routes as $route) { 
    if (!empty($route['polyline'])) { 
        // Encode polyline safely for JS
        $polyline = json_encode($route['polyline']);
        $routeId =  json_encode(intval($route['route_id']));  // Ensure it's set and a valid number
        $routeName = json_encode($route['NAME'], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);  // Encode safely for JS
        $distance = json_encode(round($route['distance'], 1) . " km"); // Ensure correct formatting
        $distance_clear = json_encode(round($route['distance'], 1)); // Ensure correct formatting
        $elevation = json_encode(round($route['elevation_gain'], 1) . " m"); // Ensure correct formatting
        $elevation_clear = json_encode(round($route['elevation_gain'], 1)); // Ensure correct formatting
        
 ?>

   // console.log("Processing route:", <?php echo $routeName; ?>, "ID:", <?php echo $routeId; ?>);

        var decodedPolyline = polyline.decode(<?php echo $polyline; ?>);
        var latlngs = decodedPolyline.map(p => [p[0], p[1]]);
        
        var routePolyline = L.polyline(latlngs, { 
            color: colors[colorIndex % colors.length], // Cycle through colors
            weight: 4 
        }).addTo(map);
        
        var popupContent = "<b>" + <?php echo $routeName; ?> + "</b><br>Distance: " + <?php echo $distance; ?> + "</b><br>Elevation: " + <?php echo $elevation; ?>;
        routePolyline.bindPopup(popupContent); // Attach popup with route name

        // Store route layer in the routeLayers array
        routeLayers.push({
            id: <?php echo $routeId; ?>,
            name: <?php echo $routeName; ?>,
            distance: <?php echo $distance_clear; ?>,
            elevation: <?php echo $elevation_clear; ?>,
            polyline: routePolyline
        });

        colorIndex++; // Move to the next color
        
        map.fitBounds(routePolyline.getBounds());

<?php } } ?>


    </script>

<script>
    $(document).ready(function() {
        $('#tags').select2({
            placeholder: "Select tags",
            allowClear: true
        });
    });
</script>

<script>
var currentMinDistance = 0,
    currentMaxDistance = <?php echo $maxDistance; ?>,
    currentMinElevation = 0,
    currentMaxElevation = <?php echo $maxElevation; ?>;

// ---------- Initialize Distance Slider ----------
var distanceSlider = document.getElementById("distanceSlider");
noUiSlider.create(distanceSlider, {
    start: [0, <?php echo $maxDistance; ?>],
    connect: true,
    range: {
        'min': 0,
        'max': <?php echo $maxDistance; ?>
    },
    step: 1,
    tooltips: [true, true],
    format: {
        to: function (value) { return Math.round(value); },
        from: function (value) { return Number(value); }
    }
});

var minDistanceLabel = document.getElementById("minDistanceLabel");
var maxDistanceLabel = document.getElementById("maxDistanceLabel");

distanceSlider.noUiSlider.on('update', function (values) {
    currentMinDistance = parseInt(values[0]);
    currentMaxDistance = parseInt(values[1]);
    minDistanceLabel.textContent = currentMinDistance;
    maxDistanceLabel.textContent = currentMaxDistance;
    filterRoutes();
});

// ---------- Initialize Elevation Slider ----------
var elevationSlider = document.getElementById("elevationSlider");
noUiSlider.create(elevationSlider, {
    start: [0, <?php echo $maxElevation; ?>],
    connect: true,
    range: {
        'min': 0,
        'max': <?php echo $maxElevation; ?>
    },
    step: 1,
    tooltips: [true, true],
    format: {
        to: function (value) { return Math.round(value); },
        from: function (value) { return Number(value); }
    }
});
 

var minElevationLabel = document.getElementById("minElevationLabel");
var maxElevationLabel = document.getElementById("maxElevationLabel");

elevationSlider.noUiSlider.on('update', function (values) {
    currentMinElevation = parseInt(values[0]);
    currentMaxElevation = parseInt(values[1]);
    minElevationLabel.textContent = currentMinElevation;
    maxElevationLabel.textContent = currentMaxElevation;
    filterRoutes();
});

// ---------- Combined Filtering Function ----------
function filterRoutes() {
    // Loop through each stored route layer and check both distance and elevation criteria.
    routeLayers.forEach(function(route) {
          //console.log("Processing route:",route.distance,route.elevation)
        // Check if route meets both filters:
        if (route.distance >= currentMinDistance && route.distance <= currentMaxDistance &&
            route.elevation >= currentMinElevation && route.elevation <= currentMaxElevation) {
            // Add route to map if not already added
            if (!map.hasLayer(route.polyline)) {
                route.polyline.addTo(map);
            }
        } else {
            // Remove route if it doesn't meet the criteria
            if (map.hasLayer(route.polyline)) {
                map.removeLayer(route.polyline);
            }
        }
    });
}



</script>




</body>
</html>