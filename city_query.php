<?php
// ==========================================
// 1. BACKEND: HANDLE LOCAL DATABASE QUERY
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'search') {
    header('Content-Type: application/json');

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

    $searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';

    // Only query if the user types 2 or more characters
    if (strlen($searchQuery) < 2) {
        echo json_encode([]);
        exit;
    }

    // Prepare SQL Statement using standard partial matching (LIKE)
    // We append '%' to the end so it looks for names starting with the query
    $stmt = $pdo->prepare("
        SELECT name, admin1_code, country_code 
        FROM cities 
        WHERE name LIKE :query 
        ORDER BY population DESC 
        LIMIT 8
    ");
    
    $stmt->execute(['query' => $searchQuery . '%']);
    $rows = $stmt->fetchAll();

    $suggestions = [];
    foreach ($rows as $row) {
        // Construct a clean, descriptive label
        $label = $row['name'];
        if ($row['admin1_code'] || $row['country_code']) {
            $label .= " — " . trim("{$row['admin1_code']} ({$row['country_code']})", " ()");
        }

        $suggestions[] = [
            'name'  => $row['name'],
            'label' => $label
        ];
    }

    echo json_encode($suggestions);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local Database Autocomplete</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 50px 20px;
            display: flex;
            justify-content: center;
        }
        .search-container {
            width: 100%;
            max-width: 400px;
            position: relative;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        input[type="text"]:focus {
            border-color: #28a745; /* Green focus to differentiate local layout */
        }
        .suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 6px 6px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        .suggestion-item {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 14px;
            color: #495057;
            border-bottom: 1px solid #f1f3f5;
        }
        .suggestion-item:last-child {
            border-bottom: none;
        }
        .suggestion-item:hover {
            background-color: #e8f5e9;
            color: #28a745;
        }
    </style>
</head>
<body>

<div class="search-container">
    <label for="city-search">Search Local Cities:</label>
    <input type="text" id="city-search" placeholder="Type a city name (e.g., Paris)..." autocomplete="off">
    <div id="suggestions" class="suggestions-dropdown"></div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const searchInput = document.getElementById("city-search");
    const suggestionsBox = document.getElementById("suggestions");
    let debounceTimer;

    searchInput.addEventListener("input", () => {
        const query = searchInput.value.trim();

        clearTimeout(debounceTimer);

        if (query.length < 2) {
            suggestionsBox.innerHTML = "";
            suggestionsBox.style.display = "none";
            return;
        }

        // Wait 250ms after user finishes typing to prevent overloading MySQL
        debounceTimer = setTimeout(() => {
            fetch(`index.php?action=search&q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    suggestionsBox.innerHTML = "";

                    if (data.length === 0 || data.error) {
                        suggestionsBox.style.display = "none";
                        return;
                    }

                    data.forEach(item => {
                        const div = document.createElement("div");
                        div.className = "suggestion-item";
                        div.textContent = item.label;
                        
                        div.addEventListener("click", () => {
                            searchInput.value = item.name;
                            suggestionsBox.innerHTML = "";
                            suggestionsBox.style.display = "none";
                        });

                        suggestionsBox.appendChild(div);
                    });

                    suggestionsBox.style.display = "block";
                })
                .catch(err => console.error("Error fetching cities:", err));
        }, 250);
    });

    // Close dropdown on clicking outside
    document.addEventListener("click", (e) => {
        if (e.target !== searchInput && e.target !== suggestionsBox) {
            suggestionsBox.style.display = "none";
        }
    });
});
</script>

</body>
</html>
