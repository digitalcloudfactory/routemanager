<?php
// callback.php
session_start();
header('Content-Type: text/html; charset=UTF-8');

// --- CONFIG ---
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'routes';
$db_user = '68a00bc6768780007ea0fea26ffa';
$db_pass = '069668a0-0bc6-788a-8000-597667343eee';
$client_id     = '6839';
$client_secret = '1a1057defe991fd6c2711f1199a3563cb3d5395f';
$redirect_uri  = 'http://map-routes.wasmer.app/callback.php';

// --- CONNECT DB ---
try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}

// --- GET AUTH CODE ---
if (!isset($_GET['code'])) {
    die("No code returned from Strava.");
}

$code = $_GET['code'];

// --- EXCHANGE CODE FOR TOKEN ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/oauth/token");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'client_id' => $strava_client_id,
    'client_secret' => $strava_client_secret,
    'code' => $code,
    'grant_type' => 'authorization_code'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data['access_token'])) {
    die("Failed to get access token from Strava.");
}

$accessToken = $data['access_token'];
$refreshToken = $data['refresh_token'];
$expiresAt = $data['expires_at'];

// --- FETCH ATHLETE INFO ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/api/v3/athlete");
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$athleteResponse = curl_exec($ch);
curl_close($ch);

$athlete = json_decode($athleteResponse, true);
if (!isset($athlete['id'])) {
    die("Failed to fetch athlete info from Strava.");
}

$stravaId = $athlete['id'];
$firstname = $athlete['firstname'] ?? '';
$lastname = $athlete['lastname'] ?? '';
$avatar = $athlete['profile'] ?? '';

// --- CHECK IF USER EXISTS IN DB ---
$stmt = $pdo->prepare("SELECT id FROM users WHERE strava_id = :strava_id");
$stmt->execute([':strava_id' => $stravaId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // User exists → update tokens and profile
    $internalUserId = $user['id'];
    $update = $pdo->prepare("
        UPDATE users SET 
            access_token = :access,
            refresh_token = :refresh,
            token_expires_at = :expires,
            firstname = :firstname,
            lastname = :lastname,
            avatar = :avatar
        WHERE id = :id
    ");
    $update->execute([
        ':access' => $accessToken,
        ':refresh' => $refreshToken,
        ':expires' => $expiresAt,
        ':firstname' => $firstname,
        ':lastname' => $lastname,
        ':avatar' => $avatar,
        ':id' => $internalUserId
    ]);
} else {
    // User does not exist → insert
    $insert = $pdo->prepare("
        INSERT INTO users 
            (strava_id, access_token, refresh_token, token_expires_at, firstname, lastname, avatar)
        VALUES 
            (:strava_id, :access, :refresh, :expires, :firstname, :lastname, :avatar)
    ");
    $insert->execute([
        ':strava_id' => $stravaId,
        ':access' => $accessToken,
        ':refresh' => $refreshToken,
        ':expires' => $expiresAt,
        ':firstname' => $firstname,
        ':lastname' => $lastname,
        ':avatar' => $avatar
    ]);

    $internalUserId = $pdo->lastInsertId();
}

// --- STORE BOTH IDS IN SESSION ---
$_SESSION['strava_id'] = $stravaId;
$_SESSION['internal_user_id'] = $internalUserId;

// --- REDIRECT TO ROUTES PAGE ---
header("Location: routes.php");
exit;
