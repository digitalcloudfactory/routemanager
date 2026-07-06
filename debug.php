<?php
// Force error reporting to screen
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Initialize your new database session architecture completely via config
require_once 'config.php'; 

echo "<h2>============= STAGE 1: BROWSER & DATABASE SESSION APPLICATION =============</h2>";
echo "<strong>Current Server Time (Unix):</strong> " . time() . " (" . date('Y-m-d H:i:s') . ")<br>";

$browserSessionId = session_id();
echo "<strong>Browser Session ID (Cookie value):</strong> " . $browserSessionId . "<br>";
echo "<strong>Active Session PHP Array:</strong> <pre>" . print_r($_SESSION, true) . "</pre>";

// --- INSPECT THE CUSTOM SESSIONS TABLE LIVE ---
echo "<h3>--- Live MySQL sessions Table Check ---</h3>";
try {
    $sessionStmt = $pdo->prepare("SELECT id, access, data FROM sessions WHERE id = ?");
    $sessionStmt->execute([$browserSessionId]);
    $dbSession = $sessionStmt->fetch(PDO::FETCH_ASSOC);

    if ($dbSession) {
        echo "<span style='color:green;'>✅ MATCH FOUND: This session ID is actively recorded in your database 'sessions' table.</span><br>";
        echo "<strong>Last Activity Timestamp:</strong> " . date('Y-m-d H:i:s', $dbSession['access']) . "<br>";
        echo "<strong>Raw Database Session Contents:</strong> <pre>" . htmlspecialchars($dbSession['data']) . "</pre>";
    } else {
        echo "<span style='color:red;'>🛑 CRITICAL FAILURE: The browser sent a session ID, but NO corresponding row exists in your MySQL 'sessions' table. The session was purged or never written.</span><br>";
    }
} catch (PDOException $e) {
    echo "<span style='color:red;'>🛑 DATABASE ERROR querying sessions table: " . $e->getMessage() . "</span><br>";
}

if (!isset($_SESSION['internal_user_id'])) {
    die("<br><span style='color:red;'>🛑 DIAGNOSTIC HALTED: No internal_user_id found in active session state. Cannot proceed with user checks.</span>");
}

echo "<h2>============= STAGE 2: DATABASE RECORD INSPECTION =============</h2>";
$internalUserId = $_SESSION['internal_user_id'];
echo "<strong>Target internal_user_id to evaluate:</strong> $internalUserId <br>";

$stmt = $pdo->prepare("SELECT access_token, refresh_token, token_expires_at, firstname, lastname FROM users WHERE id = ?");
$stmt->execute([$internalUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("<span style='color:red;'>🛑 CRITICAL FAILURE: internal_user_id ($internalUserId) exists in session, but no matching row exists in your 'users' table.</span>");
}

echo "<strong>Logged Athlete:</strong> " . htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) . "<br>";
echo "<strong>User Protected Token Data:</strong><pre>" . print_r([
    'access_token' => substr($user['access_token'], 0, 8) . '...',
    'refresh_token' => substr($user['refresh_token'], 0, 8) . '...',
    'token_expires_at' => $user['token_expires_at']
], true) . "</pre>";

$expiresAt = (int)$user['token_expires_at'];
$secondsLeft = $expiresAt - time();

echo "<strong>Token Expiration Time:</strong> " . date('Y-m-d H:i:s', $expiresAt) . "<br>";
if ($secondsLeft > 0) {
    echo "<strong>Status:</strong> <span style='color:green;'>Token is still valid for " . round($secondsLeft / 60) . " more minutes.</span><br>";
} else {
    echo "<strong>Status:</strong> <span style='color:orange;'>Token is EXPIRED by " . abs(round($secondsLeft / 60)) . " minutes. Renewal loop will trigger on next user home access.</span><br>";
}

echo "<h2>============= STAGE 3: TEST DRY-RUN REFRESH CALL =============</h2>";
echo "Attempting a live test-refresh API call to Strava using stored refresh token...<br>";

if (empty($user['refresh_token'])) {
    die("<span style='color:red;'>🛑 FAILURE: Refresh token column is empty for this user. Cannot execute refresh call.</span>");
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/oauth/token");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id'     => $strava_client_id,
    'client_secret' => $strava_client_secret,
    'grant_type'    => 'refresh_token',
    'refresh_token' => $user['refresh_token']
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<strong>Strava API HTTP Status Code:</strong> $httpCode<br>";

$data = json_decode($response, true);
if ($httpCode === 200 && isset($data['access_token'])) {
    echo "<span style='color:green;'>✅ SUCCESS: Strava accepted the tokens via your global variables and returned a fresh token pair flawlessly!</span>";
    echo "<pre>Returned scope: " . htmlspecialchars($data['scope'] ?? 'None declared') . "</pre>";
} else {
    echo "<span style='color:red;'>🛑 FAILURE: Strava rejected the refresh configuration request. Payload returned:</span>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
}