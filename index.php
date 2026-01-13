<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Strava Routes</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container">


<?php
session_start();

// Strava API credentials
$client_id = '6839';
$redirect_uri = 'http://map-routes.wasmer.app/callback.php'; // Must match callback.php

// If already logged in, redirect to activities
if (isset($_SESSION['access_token'])) {
    header("Location: activities.php");
    exit;
}

// Redirect user to Strava authorization page
$auth_url = "https://www.strava.com/oauth/authorize" .
    "?client_id={$client_id}" .
    "&response_type=code" .
    "&redirect_uri={$redirect_uri}" .
    "&approval_prompt=auto" .
    "&scope=activity:read_all";
?>

    <div class="login-section">
    <h1>Login with Strava</h1>
    <p>Connect your Strava account to view your routes</p>
    <a class="strava-button" href="<?= $auth_url ?>">Login</a>
</div>
</div></body></html>
