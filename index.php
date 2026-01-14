<?php include 'header.php'; ?>
<style>
  .strava-button {
  display: inline-flex;
  align-items: center;
  background: #fc4c02;
  color: white;
  padding: 0.8rem 1.5rem;
  border-radius: 8px;
  font-weight: 600;
  text-decoration: none;
  transition: background 0.3s;
}

.strava-button:hover {
  background: #e34402;
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
    <h1>Login with Strava</h1>
    <p>Connect your Strava account to view your routes</p>


      
    <a class="strava-button" href="<?= $auth_url ?>"><img src="https://www.dropbox.com/scl/fi/rzrnbkndn8y2u8if4hezd/btn_strava_connect_with_orange.png?rlkey=s0w9ewb5o9fimgsh33ekqt9lz&dl=0"
    alt="Strava" style="height:1.2em; vertical-align:middle; margin-right:0.5em;"></a>
</div>
</div>

<?php include 'footer.php'; ?>
