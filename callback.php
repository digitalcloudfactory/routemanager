<?php
session_start();

/* ===============================
   CONFIG
================================ */

$client_id     = '6839';
$client_secret = '1a1057defe991fd6c2711f1199a3563cb3d5395f';
$redirect_uri  = 'http://map-routes.wasmer.app/callback.php';

// Database credentials
$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'routes';
$db_user = '68a00bc6768780007ea0fea26ffa';
$db_pass = '069668a0-0bc6-788a-8000-597667343eee';

/* ===============================
   VALIDATE OAUTH RESPONSE
================================ */

if (!isset($_GET['code'])) {
    http_response_code(400);
    echo "Missing authorization code.";
    exit;
}

$code = $_GET['code'];

/* ===============================
   EXCHANGE CODE FOR TOKEN
================================ */

$token_url = "https://www.strava.com/oauth/token";

$post_data = [
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'code'          => $code,
    'grant_type'    => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($post_data),
    CURLOPT_RETURNTRANSFER => true
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

if (!isset($data['access_token'], $data['athlete']['id'])) {
    http_response_code(500);
    echo "Failed to authenticate with Strava.";
    exit;
}

/* ===============================
   EXTRACT USER & TOKEN DATA
================================ */

$strava_id    = $data['athlete']['id'];
$access_token = $data['access_token'];
$refresh_token= $data['refresh_token'];
$expires_at   = $data['expires_at'];
$firstname = $athlete['firstname'];
$lastname  = $athlete['lastname'];
$avatar    = $athlete['profile'];


/* ===============================
   CONNECT TO DATABASE (UTF8MB4)
================================ */

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
    http_response_code(500);
    echo "Database connection failed.";
    exit;
}

/* ===============================
   STORE / UPDATE USER TOKENS
================================ */

$stmt = $pdo->prepare("
  INSERT INTO users
    (strava_id, firstname, lastname, avatar, access_token, refresh_token, expires_at)
  VALUES
    (:sid, :first, :last, :avatar, :access, :refresh, :expires)
  ON DUPLICATE KEY UPDATE
    firstname = VALUES(firstname),
    lastname  = VALUES(lastname),
    avatar    = VALUES(avatar),
    access_token = VALUES(access_token),
    refresh_token = VALUES(refresh_token),
    expires_at = VALUES(expires_at)
");

$stmt->execute([
  ':sid'     => $strava_id,
  ':first'   => $firstname,
  ':last'    => $lastname,
  ':avatar'  => $avatar,
  ':access'  => $access_token,
  ':refresh' => $refresh_token,
  ':expires' => $expires_at
]);


/* ===============================
   CREATE USER SESSION
================================ */

$_SESSION['user_id'] = $strava_id;

/* ===============================
   REDIRECT TO DASHBOARD
================================ */

header("Location: routes.php");
exit;
