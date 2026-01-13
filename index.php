<?php
session_start();

// Strava API credentials
$client_id = 'YOUR_STRAVA_CLIENT_ID';
$redirect_uri = 'http://localhost:8080/callback.php'; // Must match callback.php

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

echo "<h1>Login with Strava</h1>";
echo "<a href='$auth_url'>Login</a>";
?>
