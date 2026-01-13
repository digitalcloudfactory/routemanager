<?php
session_start();

$client_id = '6839';
$client_secret = '1a1057defe991fd6c2711f1199a3563cb3d5395f';
$redirect_uri = 'http://map-routes.wasmer.app/callback.php';

if (!isset($_GET['code'])) {
    echo "No code returned from Strava.";
    exit;
}

$code = $_GET['code'];

// Exchange code for access token
$token_url = "https://www.strava.com/oauth/token";
$data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $code,
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$json = json_decode($response, true);

if (isset($json['access_token'])) {
    $_SESSION['access_token'] = $json['access_token'];
    header("Location: activities.php");
    exit;
} else {
    echo "Error fetching access token: " . $response;
}
?>
