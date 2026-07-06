<?php
// config.php

// 1. Force error reporting for development (you can disable this later)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Database Connection Credentials
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
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

// 3. Inject the Custom MySQL Session Handler
require_once 'session_handler.php';
$handler = new MySqlSessionHandler($pdo);
session_set_save_handler($handler, true);

// 4. Configure Long-Lived Browser Cookie Parameters (2 weeks)
session_set_cookie_params([
    'lifetime' => 1209600,
    'path' => '/',
    'domain' => '', 
    'secure' => true, // Secure flag enabled for your https://map-routes.wasmer.app domain
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// 5. Shared Strava API Credentials
$strava_client_id = '6839';
$strava_client_secret = '1a1057defe991fd6c2711f1199a3563cb3d5395f';
$redirect_uri  = 'http://map-routes.wasmer.app/callback.php';