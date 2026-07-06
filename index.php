<?php include 'header.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    html, body {
      height: 100%;
      margin: 0;
      font-family: 'Inter', sans-serif;
    }

    body {
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: #f4f6f9; /* Matches the slate grey planner layout */
      position: relative;
    }

    /* Subtle backdrop graphics structure variant pattern loop wrapper */
    .login-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
      width: 100%;
      min-height: 100vh;
      padding: 1.5rem;
    }

    .login-container {
      background: #ffffff; /* White profile dashboard base */
      padding: 3rem 2.5rem;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 10px 30px rgba(148, 163, 184, 0.12); /* Clean light drop shadow */
      max-width: 420px;
      width: 100%;
      text-align: center;
      transition: y-axis 0.3s ease;
    }

    .login-container h1 {
      font-size: 1.75rem;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 0.75rem;
      letter-spacing: -0.5px;
    }
    
    .login-container h1 span {
      color: #0284c7; /* Canyon Blue accent indicator match */
    }

    .login-container p {
      font-size: 0.95rem;
      color: #64748b; /* Slate secondary metadata text */
      margin-bottom: 2.25rem;
      line-height: 1.5;
    }

    .strava-button-wrapper {
      display: inline-block;
      text-decoration: none !important;
      width: 100%;
    }

    .strava-button-wrapper img {
      max-width: 240px;
      width: 100%;
      height: auto;
      border-radius: 6px;
      box-shadow: 0 4px 12px rgba(252, 76, 2, 0.15); /* Styled drop shadow matching Strava Orange */
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }

    .strava-button-wrapper img:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 18px rgba(252, 76, 2, 0.25);
    }
    
    .strava-button-wrapper img:active {
      transform: translateY(0);
    }
</style>

<body>
<div class="login-wrapper">

<?php
require_once 'config.php'; // 🟩 Everything loads instantly

error_log('Session ID: ' . session_id());
error_log('Session contents: ' . print_r($_SESSION, true));

$needsAuth = true;

if (isset($_SESSION['internal_user_id'])) {
    // 1. Fetch access token using the global $pdo instance
    $stmt = $pdo->prepare("
        SELECT access_token, refresh_token, token_expires_at 
        FROM users 
        WHERE id = ?
    ");

$needsAuth = true;

    $stmt->execute([$_SESSION['internal_user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['access_token']) {
        if (time() >= ($row['token_expires_at'] - 300)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.strava.com/oauth/token");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'client_id'     => $client_id,
                'client_secret' => '1a1057defe991fd6c2711f1199a3563cb3d5395f', 
                'grant_type'    => 'refresh_token',
                'refresh_token' => $row['refresh_token']
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if (isset($data['access_token'])) {
                $updateStmt = $pdo->prepare("
                    UPDATE users 
                    SET access_token = ?, refresh_token = ?, token_expires_at = ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $data['access_token'],
                    $data['refresh_token'],
                    $data['expires_at'],
                    $_SESSION['internal_user_id']
                ]);
                
                header("Location: routes.php");
                exit;
            } else {
                $needsAuth = true;
            }
        } else {
            header("Location: routes.php");
            exit;
        }
    }
}

if ($needsAuth) {
    $auth_url = "https://www.strava.com/oauth/authorize" .
        "?client_id={$client_id}" .
        "&response_type=code" .
        "&redirect_uri=" . urlencode($redirect_uri) .
        "&approval_prompt=auto" .
        "&scope=read_all";
}
?>

  <section class="login-container">
    <h1>Strava <span>Routes</span></h1>
    <p>Connect your athlete profile parameters seamlessly to analyze your track routes and schedule strategic refueling stops.</p>

   <?php if ($needsAuth): ?>
     <a class="strava-button-wrapper" href="<?= htmlspecialchars($auth_url) ?>">
        <img src="https://www.dropbox.com/scl/fi/rzrnbkndn8y2u8if4hezd/btn_strava_connect_with_orange.png?rlkey=s0w9ewb5o9fimgsh33ekqt9lz&dl=1" alt="Connect with Strava">
     </a>
   <?php endif; ?>
  </section>

</div>

<?php include 'footer.php'; ?>
