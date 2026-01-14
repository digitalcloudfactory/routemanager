<?php include 'header.php'; ?>
<style>

    body {
      display: flex;
      align-items: center;
      justify-content: center;
      background: url('https://www.dropbox.com/scl/fi/bk21xinnt8rvgffmycfsj/mallorca-1117793_1280.jpg?rlkey=61yqez5zhfxqjss96fx37lqcd&dl=1') no-repeat center center fixed;
      background-size: cover;
      position: relative;
    }

    /* semi-transparent overlay */
    body::before {
      content: "";
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background-color: rgba(255, 255, 255, 0.4); /* adjust opacity to lighten image */
      z-index: 0;
    }
  
.strava-button {
  display: inline-block;
  text-decoration: none;
}
.strava-button:hover {
  background: #e34402;
}

  .login-section {
    z-index: 1;
  text-align: center;
  padding: 4rem 2rem;
}

.login-section a.strava-button {
  z-index: 1;
  display: inline-block;
  margin-top: 2rem;
}
  </style>

<body>
<!-- Header -->
  <header class="grid">
    <div style="text-align:right">
      <button id="themeToggle" class="secondary outline">
        🌙 Dark mode
      </button>
    </div>
  </header>

<div class="container">


<?php
session_start();

// Strava API credentials
$client_id = '6839';
$redirect_uri = 'http://map-routes.wasmer.app/callback.php'; // Must match callback.php

// If already logged in, redirect to activities
if (isset($_SESSION['access_token'])) {
    header("Location: routes.php");
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
   <h1>Welcome to Strava Routes</h1>
    <p>Connect your Strava account to view and manage your routes.</p>

    <a class="strava-button" href="<?= $auth_url ?>">
    <img src="https://www.dropbox.com/scl/fi/rzrnbkndn8y2u8if4hezd/btn_strava_connect_with_orange.png?rlkey=s0w9ewb5o9fimgsh33ekqt9lz&dl=1" 
         alt="Connect with Strava" style="max-width:250px; width:100%; height:auto;">
  </a>
</div>
</div>

<?php include 'footer.php'; ?>
