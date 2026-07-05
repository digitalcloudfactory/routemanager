<?php
// Force error reporting to screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Long-lived session configurations
session_set_cookie_params([
    'lifetime' => 1209600,
    'path' => '/',
    'domain' => '', 
    'secure' => false, 
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// --- HARDCODED CREDENTIALS FOR STANDALONE TESTING ---
$client_id = '6839';
$strava_client_secret = '1a1057defe991fd6c2711f1199a3563cb3d5395f';

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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

echo "<h2>============= STAGE 1: BROWSER & SESSION APPLICATION =============</h2>";
echo "<strong>Current Server Time (Unix):</strong> " . time() . " (" . date('Y-m-d H:i:s') . ")<br>";
echo "<strong>Browser Session ID:</strong> " . session_id() . "<br>";
echo "<strong>Session Contents:</strong> <pre>" . print_r($_SESSION, true) . "</pre>";

if (!isset($_SESSION['internal_user_id'])) {
    die("<span style='color:red;'>🛑 CRITICAL FAILURE: No internal_user_id found in session. Your browser or server is dropping the session cookie.</span>");
}

echo "<h2>============= STAGE 2: DATABASE RECORD INSPECTION =============</h2>";
$stmt = $pdo->prepare("SELECT access_token, refresh_token, token_expires_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['internal_user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("<span style='color:red;'>🛑 CRITICAL FAILURE: User ID found in session, but no matching row exists in the database.</span>");
}

echo "<strong>Database Row Data:</strong><pre>" . print_r($user, true) . "</pre>";

$expiresAt = (int)$user['token_expires_at'];
$secondsLeft = $expiresAt - time();

echo "<strong>Token Expiration Time:</strong> " . date('Y-m-d H:i:s', $expiresAt) . "<br>";
if ($secondsLeft > 0) {
    echo "<strong>Status:</strong> <span style='color:green;'>Token is still valid for " . round($secondsLeft / 60) . " more minutes.</span><br>";
} else {
    echo "<strong>Status:</strong> <span style='color:orange;'>Token is EXPIRED by " . abs(round($secondsLeft / 60)) . " minutes. Renewal should trigger.</span><br>";
}

echo "<h2>============= STAGE 3: TEST DRY-RUN REFRESH CALL =============</h2>";
echo "Attempting a live test-refresh API call to Strava using stored refresh token...<br>";

if (empty($user['refresh_token'])) {
    die("<span style='color:red;'>🛑 FAILURE: Refresh token column is empty. Cannot refresh.</span>");
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/oauth/token");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => $client_id,
    'client_secret' => $strava_client_secret,
    'grant_type'    => 'refresh_token',
    'refresh_token' => $user['refresh_token']
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<strong>Strava API HTTP Status Code:</strong> $httpCode<br>";
echo "<strong>Strava Response Payload:</strong><pre>" . htmlspecialchars($response) . "</pre>";

$data = json_decode($response, true);
if (isset($data['access_token'])) {
    echo "<span style='color:green;'>✅ SUCCESS: Strava accepted the refresh token and handed back a new access token seamlessly!</span>";
} else {
    echo "<span style='color:red;'>🛑 FAILURE: Strava rejected the refresh token configuration. Check the payload message above.</span>";
}
