<?php
/* ===============================
----Important rule going forward----
-Use case-	            -ID to use-
DB queries	            internal_user_id ✅
Strava API calls	    strava_id
Session auth check	    internal_user_id
================================ */
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['internal_user_id'])) {
    header("Location: index.php");
    exit;
}
$internalUserId = $_SESSION['internal_user_id'];

/* ===============================
   DATABASE CONFIG
================================ */

$db_host = 'db.fr-pari1.bengt.wasmernet.com';
$db_port = 10272;
$db_name = 'dbcmpLT2zrmwmur5UEjZ3Xj8';
$db_user = 'de142c5d7a0180009884f0319fb7';
$db_pass = '0696de14-2c5d-7bb2-8000-fe77e5a731bf';

$pdo = new PDO(
    "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4",
    $db_user,
    $db_pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]
);

// Query to get all routes for the user
$stmt = $pdo->prepare("SELECT route_id, name, summary_polyline, distance_km FROM strava_routes WHERE user_id = ?");
$stmt->execute([$internalUserId]);
$allRoutes = $stmt->fetchAll(PDO::FETCH_ASSOC);




// PHP only provides encoded polylines (no geometry math here)
$encoded1 = 'kzehG`lrH~FpAhCbDxI~^rc@p_@~EG~B`IxLz@vAlGbE`F\lKj`@l\v[vd@~@vF~DItSrGtHSfElKxLpDb]th@`EpRjpA~nCrF|JxEpBOnGbb@p_A`HiEta@kDfEqBvE_MFoh@tK{QrP}Orj@t@dVmMfX|D`KeC~QwVbXiHvD{ChJy[pLaAnEqJtSuMlDaIv@kQlP[dF_CzI`@xI}DfDeJfEkEy@}Kd@mGbDyApNu`@lU}DrMyFjDdBdJwH`CtA~@wIlEqAbDzChNkJBoFbLkLqDcMuEcDaCFoAuClFoDzKg@oM_CnD}DrCkPlDkEpH~ErK?nMpIdUfb@vGxF~G}@lLyPpFiA`GpAdm@rc@bAzGdG|GnNh]h@zKvEjIOdMzCdElBvRRdNsEnRt@nd@nJxWzDdGxF`Cc@pBcDQ}DdDgC`FyAlQ|BlKuBrI}J|DiF`GbCfNfSzXfF|[i@bJiDrKt@hOgCtClDzXeChm@n@nJjCdI@|OaPfG{Fi@qCtC{GiAiJ~BoCdLl@vFcIhGpBzCEhDmAD~@z@xAgDQuDhFB{CT^bEqAbHsDG{DnKqC_@m@tDuC|AgA{A{BdCmAyEaFuC}ICiEzDuDeFoP|A_JuEmD`IuCuDkEvAgAcBmBl@b@uGzCiAVsBaEcEMkEwB{DyLdCy^bA{T?yC_E{ClI{JlCiDiAaEzB}Cq@mMjBkEiEnCwIe@cKbAqEiBrB[hE}A`@E~DaEtAmEtGq@jEaK`A{CyrAbAcKUsMyC{GmE_Bs@wB_@mFnBuJ?iH}XecCeHmGaBaGd@aKmBiHnAeJ}EySaAc[{CyOoKhAeKvKkCqAiKZE{JoCiHaRmJyQg\oOoQcBaIq@wh@gZkb@u@{XtEcWlBaGdHiHlHoYhPyY~Eel@dFwOuHSmHlFwEG}OcLkLcOsDh@wAdFoCnAmPsKqH{CsEXkHqH}DMiMrBuS`TmKRyDeH}P}Eg^hP_FhG{Cn@_IcGqCpAuBcB}BhBaI}VcLqEwEtNiG|Hc@|FmFvHoIy@l@xEc[hOcFh@sKpI}JwAmXfF{Ux`@}Bu@{EmM_R`CwCiDqByJ}LeCuRVc]hGke@}GiJfCmJ}AwHeJgGaC';
$encoded2 = 'ezehG`lrHxFpAhCbDxI~^rc@p_@~EG~B`IxLz@vAlGbE`F\lKj`@l\v[vd@~@vF~DItSrGtHSfElKxLpDb]th@`EpRjpA~nCrF|JxEpBOnGn]|s@`I~WtKxFdHzU|DfC|@{Bx@j@tGnSs@vDlDnEhKZnNdLzDxFdD|OjIxTlRpTt@hDxQlJtElNtNdL|G|KxExNfBKzA_FvElF~NcAtv@gU|DrBjBmBdCLjJdFra@jEfJcAzJpA~IuGtDyL`KaAp@kElEuG`EuAD_E|Aa@ZiEhBsBcApEd@bKoCvIjEhElMkB|Cp@`E{BhDhAzJmCzCmIxC~DzT?x^cAxLeCvBzDLjE`EbEWrB{ChAc@tGlBm@fAbBjEwAtCtD`DcIjJvEnP}AtDdFhE{D|IB`FtClAxEzBeCfAzAtC}Al@uDpC^zDoKrDFpAcH_@cEzCUiFCPtDyAfD_A{@lAEDiDqB{CbIiGm@wFnCeLhJ_CzGhApCuCzFh@`PgGn@pMtBrB`C~LnA~[zF`ChSqA|@vLzF|@|MqKpDuJvBgO~EmFxBE~R|KrLZrFcDrGkTnIiJfDW~QhHtNyB~BuG_@qVxBgHp^a\tRdHdAuBxO_CNmBeGcFi@eExKwDoAyNz@wE~PeHhBoH}CwDeCgNrD_RxEiGc@mXsCc^nDkPbGeBTsJyLsEyAiEeIsFaFo@K{GyBiHyL_BqIqPyJcDb@sJ_FoGqA_GmEcDjCLVgGe@}DmCsCWeEbGpHpFfRoA{CJ}EuSql@qBxFyC{GcDnDi@nHt@pHlC~EaD{CyB{J|@{OkCzNj@hH{Kf@oGhRmDrDvBtG?bLxC`CDlBqPkOsFmUiC~AkCkAgIdBeAoGtBxDnAk@tEwHxC{LjCeATsBe@s@]fCaCPgEvHb@wOiBgD{AJLqEiDwL`BeGe@cChAlAMtH|@kEo@cPqGz@uBoDuQMgHgC~@hKoK}K_Dn@qOmCiDLaCvEUdFeBLMmQeNeFiE?Lec@}HsLcCmGe@cHcUki@cLuRcb@mRgJmJ}GuNqJoC{AmDmEgByQlQsSwL_I|@gK_A{A_GfCeJs@yHkDiHgL{GwHsJeCsJsFlA{HkIaK{SmBuM}D{G}A{MiS_Ha`@o[oDpAmHfNqEnD_b@rMgVmJqS`UqNv@y@}CuNyLmIhXuOhB{Ar[_IfG|G|GWd\|AtNiAlCqIGqG`FsFOcKsHiPkRsDh@wAdFoCnA_ZoPwFDaHiHmRpB{TfTgJLyDeH}QwEg]bP{I|H_JgGqCpAuBcB}BhBaI}VcLqEwEtNiG|Hc@|FmFvHoIy@l@xEc[hOcFh@sKpI}JwAmXfF_Ur`@yCo@{EmM_R`CwCiDqByJ}LeCuRVc]hGke@}GiJfCyEi@sImG';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>Route Duplicate Finder</title>
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
<script src="https://unpkg.com/@mapbox/polyline"></script>
</head>
<body>

<div class="stats" style="margin-bottom: 20px; font-family: sans-serif;">
    <b>Duplicate Finder</b><br>
    Min Match: <input type="range" id="overlapSlider" min="10" max="100" value="80"> 
    <span id="sliderVal">80</span>%
</div>

<table id="duplicateTable" border="1" style="width:100%; border-collapse: collapse; font-family: sans-serif;">
    <thead>
        <tr style="background: #f4f4f4;">
            <th style="padding: 10px;">Route A</th>
            <th style="padding: 10px;">Route B</th>
            <th style="padding: 10px;">Overlap %</th>
        </tr>
    </thead>
    <tbody id="resultsBody"></tbody>
</table>

<script>
// 1. Data from PHP
const allRoutesData = <?= json_encode($allRoutes ?? []) ?>;

// 2. Pre-decode all routes so we don't do it inside the loop
const decodedRoutes = allRoutesData.map(r => ({
    name: r.name,
    id: r.route_id,
    latlngs: polyline.decode(r.summary_polyline).map(p => L.latLng(p[0], p[1]))
}));

// --- YOUR ORIGINAL FUNCTIONS (UNTOUCHED) ---
// Compute Haversine distance between two [lat, lon] points
function haversineDistance(p1, p2) {
  const R = 6371000; // meters
  const toRad = Math.PI / 180;
  const dLat = (p2[0] - p1[0]) * toRad;
  const dLon = (p2[1] - p1[1]) * toRad;
  const lat1 = p1[0] * toRad;
  const lat2 = p2[0] * toRad;

  const a = Math.sin(dLat/2)**2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon/2)**2;
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}

// Distance from point p to segment a-b in lat/lon meters
function pointToSegmentDistanceMeters(p, a, b) {
  const dx = b[1] - a[1]; // lon difference
  const dy = b[0] - a[0]; // lat difference

  if (dx === 0 && dy === 0) return haversineDistance(p, a);

  let t = ((p[1]-a[1])*dx + (p[0]-a[0])*dy)/(dx*dx + dy*dy);
  t = Math.max(0, Math.min(1, t));

  const proj = [a[0] + t*dy, a[1] + t*dx];
  return haversineDistance(p, proj);
}

// Distance between two segments in meters
function segmentDistanceMeters(p1a, p1b, p2a, p2b) {
  return Math.min(
    pointToSegmentDistanceMeters(p1a, p2a, p2b),
    pointToSegmentDistanceMeters(p1b, p2a, p2b),
    pointToSegmentDistanceMeters(p2a, p1a, p1b),
    pointToSegmentDistanceMeters(p2b, p1a, p1b)
  );
}


    
function findOverlap(latlngsA, latlngsB, tolerance = 8) {
  let total = 0;
  let overlap = 0;
  const segments = [];

  for (let i = 0; i < latlngsA.length - 1; i++) {
    const a1 = latlngsA[i];
    const a2 = latlngsA[i+1];

    // segment length in meters
    const segLen = haversineDistance([a1.lat, a1.lng], [a2.lat, a2.lng]);
    total += segLen;

    // check if this segment matches any in latlngsB
    let matched = false;
    for (let j = 0; j < latlngsB.length - 1; j++) {
      const b1 = latlngsB[j];
      const b2 = latlngsB[j+1];

      if (segmentDistanceMeters([a1.lat, a1.lng], [a2.lat, a2.lng],
                                [b1.lat, b1.lng], [b2.lat, b2.lng]) <= tolerance) {
        matched = true;
        break;
      }
    }

    if (matched) {
      overlap += segLen;
      segments.push([a1, a2]);
    }
  }

  return { total, overlap, percent: (overlap / total) * 100, segments };
}
// --- END ORIGINAL FUNCTIONS ---

// 3. The Logic to find duplicates in the database
function runDuplicateCheck() {
    const threshold = parseInt(document.getElementById('overlapSlider').value);
    const tbody = document.getElementById('resultsBody');
    tbody.innerHTML = "<tr><td colspan='3'>Analyzing routes...</td></tr>";

    // Small delay to allow UI to update
    setTimeout(() => {
        let html = "";
        
        // Compare every route against every other route
        for (let i = 0; i < decodedRoutes.length; i++) {
            for (let j = i + 1; j < decodedRoutes.length; j++) {
                const rA = decodedRoutes[i];
                const rB = decodedRoutes[j];

                // Run your match both ways (like your original code)
                const resA = findOverlap(rA.latlngs, rB.latlngs);
                const resB = findOverlap(rB.latlngs, rA.latlngs);
                
                // Use the conservative result (the lower of the two)
                const finalPercent = Math.min(resA.percent, resB.percent);

                if (finalPercent >= threshold) {
                    html += `<tr>
                        <td style="padding:8px;">${rA.name}</td>
                        <td style="padding:8px;">${rB.name}</td>
                        <td style="padding:8px;"><strong>${finalPercent.toFixed(1)}%</strong></td>
                    </tr>`;
                }
            }
        }
        tbody.innerHTML = html || "<tr><td colspan='3'>No matches found at this threshold.</td></tr>";
    }, 50);
}

// 4. Slider Listener
document.getElementById('overlapSlider').oninput = function() {
    document.getElementById('sliderVal').innerText = this.value;
    runDuplicateCheck();
};

// Run on load
runDuplicateCheck();

</script>
