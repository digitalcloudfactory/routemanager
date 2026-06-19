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
$handle = fopen("cities5000.txt", "r");

if ($handle) {
    // Prepare a master insert query once (for speed)
    $stmt = $pdo->prepare("INSERT INTO cities VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    
    $pdo->beginTransaction(); // Wrap in a transaction to make it 100x faster
    
    $count = 0;
    while (($line = fgets($handle)) !== false) {
        // Split the line by tabs (\t)
        $data = explode("\t", trim($line));
        
        // Ensure empty fields turn into NULL values rather than breaking
        $data = array_map(function($value) {
            return $value === '' ? null : $value;
        }, $data);

        // Execute the insert
        $stmt->execute($data);
        
        // Commit in chunks of 5,000 rows to prevent running out of memory
        if (++$count % 5000 === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
        }
    }
    
    $pdo->commit(); // Commit any remaining rows
    fclose($handle);
    echo "Import completed successfully!";
} else {
    echo "Error opening the text file.";
}
?>
