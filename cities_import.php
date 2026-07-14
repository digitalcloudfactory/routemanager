<?php

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

// Open the txt file
$handle = fopen("cities1000.txt", "r");

if ($handle) {
    // Prepare a master insert query once
    $stmt = $pdo->prepare("INSERT INTO cities VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    
    $pdo->beginTransaction();
    
    $count = 0;
    $inserted = 0;

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if (empty($line)) continue;

        // Split the line by tabs (\t)
        $data = explode("\t", $line);
        
        // Timezone is at index 17 in the GeoNames schema
        $timezone = $data[17] ?? '';

        // Check if the timezone starts with 'Europe/' (Works in PHP 8+)
        // If you are on PHP 7, use: strpos($timezone, 'Europe/') === 0
        if (str_starts_with($timezone, 'Europe/')) {
            // Ensure empty fields turn into NULL values
            $data = array_map(function($value) {
                return $value === '' ? null : $value;
            }, $data);

            $stmt->execute($data);
            $inserted++;
            
            // Commit in chunks of 5,000 inserted rows
            if (++$count % 5000 === 0) {
                $pdo->commit();
                $pdo->beginTransaction();
            }
        }
    }
    
    $pdo->commit(); // Commit remaining rows
    fclose($handle);
    echo "Import completed successfully! Total imported: $inserted";
} else {
    echo "Error opening the text file.";
}
?>