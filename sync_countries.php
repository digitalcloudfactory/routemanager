<?php

require_once 'config.php'; // 🟩 Everything loads instantly


// Access control layer: kick them out to index if they aren't authenticated
if (!isset($_SESSION['internal_user_id'])) {
    header("Location: index.php");
    exit;
}
$internalUserId = $_SESSION['internal_user_id'];

$stmt = $pdo->prepare("
    SELECT route_id, summary_polyline 
    FROM strava_routes 
    WHERE (country IS NULL OR country = '') 
    AND summary_polyline IS NOT NULL 
    LIMIT 50
");
$stmt->execute();
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updatedCount = 0;

foreach ($routes as $route) {
    $country = getCountryFromPolyline($route['summary_polyline']);
    
    if ($country) {
        $update = $pdo->prepare("UPDATE strava_routes SET country = ? WHERE route_id = ?");
        $update->execute([$country, $route['route_id']]);
        $updatedCount++;
    }

    // Still mandatory for API limits
    usleep(1200000); 
}

// 2. Output ONLY clean JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'updated_count' => $updatedCount,
    'finished' => (count($routes) === 0)
]);
exit;


// --- Helper Function ---
function getCountryFromPolyline(string $summaryPolyline): ?string {
    if (!$summaryPolyline) return null;
    $coords = decodePolyline($summaryPolyline);
    if (!$coords || count($coords) === 0) return null;
    $lat = $coords[0][0];
    $lon = $coords[0][1];
    
    // Added &accept-language=nl to the URL
    $url = "https://nominatim.openstreetmap.org/reverse"
         . "?lat=" . urlencode($lat)
         . "&lon=" . urlencode($lon)
         . "&format=json"
         . "&accept-language=nl"; // Forces Dutch naming
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['User-Agent: MapRoutesApp/1.0 (contact@yourdomain.com)'],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return null;
    $data = json_decode($res, true);
    return $data['address']['country'] ?? null;
}

function decodePolyline(string $encoded): array {
    $points = []; $index = 0; $lat = 0; $lng = 0; $len = strlen($encoded);
    while ($index < $len) {
        $b = 0; $shift = 0; $result = 0;
        do { $b = ord($encoded[$index++]) - 63; $result |= ($b & 0x1f) << $shift; $shift += 5; } while ($b >= 0x20);
        $dlat = ($result & 1) ? ~($result >> 1) : ($result >> 1); $lat += $dlat;
        $shift = 0; $result = 0;
        do { $b = ord($encoded[$index++]) - 63; $result |= ($b & 0x1f) << $shift; $shift += 5; } while ($b >= 0x20);
        $dlng = ($result & 1) ? ~($result >> 1) : ($result >> 1); $lng += $dlng;
        $points[] = [$lat / 1e5, $lng / 1e5];
    }
    return $points;
}
