<?php
// city_search.php — Fast MySQL Autocomplete Endpoint
header('Content-Type: application/json; charset=utf-8');

$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'dbcmpLT2zrmwmur5UEjZ3Xj8';
$db_user = 'de142c5d7a0180009884f0319fb7';
$db_pass = '0696de14-2c5d-7bb2-8000-fe77e5a731bf';

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

    $searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

    if (strlen($searchQuery) < 2) {
        echo json_encode([]);
        exit;
    }

    // Select lat & lng along with name and country info
    $stmt = $pdo->prepare("
        SELECT name, admin1_code, country_code, latitude, longitude
        FROM cities 
        WHERE LOWER(name) LIKE :query 
        ORDER BY population DESC 
        LIMIT 8
    ");
    
    $cleanQuery = '%' . strtolower($searchQuery) . '%';
    $stmt->execute(['query' => $cleanQuery]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $suggestions = [];
    foreach ($rows as $row) {
        $label = $row['name'];
        if (!empty($row['admin1_code']) || !empty($row['country_code'])) {
            $label .= " — " . trim("{$row['admin1_code']} ({$row['country_code']})", " ()");
        }

        $suggestions[] = [
            'name'  => $row['name'],
            'label' => $label,
            'lat'   => (float)$row['latitude'],
            'lng'   => (float)$row['longitude']
        ];
    }

    echo json_encode($suggestions);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}