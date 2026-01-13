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



echo "<h1>Strava Routes Viewer</h1>";
echo "<p>Connect your Strava account to view your routes</p>";
echo "<a href='$auth_url' class='strava-button'>Login</a>";
?>
