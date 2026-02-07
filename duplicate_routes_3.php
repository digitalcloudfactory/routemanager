<?php
// PHP only provides encoded polylines (no geometry math here)
$encoded1 = 'kzehG`lrH~FpAhCbDxI~^rc@p_@~EG~B`IxLz@vAlGbE`F\lKj`@l\v[vd@~@vF~DItSrGtHSfElKxLpDb]th@`EpRjpA~nCrF|JxEpBOnGbb@p_A`HiEta@kDfEqBvE_MFoh@tK{QrP}Orj@t@dVmMfX|D`KeC~QwVbXiHvD{ChJy[pLaAnEqJtSuMlDaIv@kQlP[dF_CzI`@xI}DfDeJfEkEy@}Kd@mGbDyApNu`@lU}DrMyFjDdBdJwH`CtA~@wIlEqAbDzChNkJBoFbLkLqDcMuEcDaCFoAuClFoDzKg@oM_CnD}DrCkPlDkEpH~ErK?nMpIdUfb@vGxF~G}@lLyPpFiA`GpAdm@rc@bAzGdG|GnNh]h@zKvEjIOdMzCdElBvRRdNsEnRt@nd@nJxWzDdGxF`Cc@pBcDQ}DdDgC`FyAlQ|BlKuBrI}J|DiF`GbCfNfSzXfF|[i@bJiDrKt@hOgCtClDzXeChm@n@nJjCdI@|OaPfG{Fi@qCtC{GiAiJ~BoCdLl@vFcIhGpBzCEhDmAD~@z@xAgDQuDhFB{CT^bEqAbHsDG{DnKqC_@m@tDuC|AgA{A{BdCmAyEaFuC}ICiEzDuDeFoP|A_JuEmD`IuCuDkEvAgAcBmBl@b@uGzCiAVsBaEcEMkEwB{DyLdCy^bA{T?yC_E{ClI{JlCiDiAaEzB}Cq@mMjBkEiEnCwIe@cKbAqEiBrB[hE}A`@E~DaEtAmEtGq@jEaK`A{CyrAbAcKUsMyC{GmE_Bs@wB_@mFnBuJ?iH}XecCeHmGaBaGd@aKmBiHnAeJ}EySaAc[{CyOoKhAeKvKkCqAiKZE{JoCiHaRmJyQg\oOoQcBaIq@wh@gZkb@u@{XtEcWlBaGdHiHlHoYhPyY~Eel@dFwOuHSmHlFwEG}OcLkLcOsDh@wAdFoCnAmPsKqH{CsEXkHqH}DMiMrBuS`TmKRyDeH}P}Eg^hP_FhG{Cn@_IcGqCpAuBcB}BhBaI}VcLqEwEtNiG|Hc@|FmFvHoIy@l@xEc[hOcFh@sKpI}JwAmXfF{Ux`@}Bu@{EmM_R`CwCiDqByJ}LeCuRVc]hGke@}GiJfCmJ}AwHeJgGaC';
$encoded2 = 'ezehG`lrHxFpAhCbDxI~^rc@p_@~EG~B`IxLz@vAlGbE`F\lKj`@l\v[vd@~@vF~DItSrGtHSfElKxLpDb]th@`EpRjpA~nCrF|JxEpBOnGn]|s@`I~WtKxFdHzU|DfC|@{Bx@j@tGnSs@vDlDnEhKZnNdLzDxFdD|OjIxTlRpTt@hDxQlJtElNtNdL|G|KxExNfBKzA_FvElF~NcAtv@gU|DrBjBmBdCLjJdFra@jEfJcAzJpA~IuGtDyL`KaAp@kElEuG`EuAD_E|Aa@ZiEhBsBcApEd@bKoCvIjEhElMkB|Cp@`E{BhDhAzJmCzCmIxC~DzT?x^cAxLeCvBzDLjE`EbEWrB{ChAc@tGlBm@fAbBjEwAtCtD`DcIjJvEnP}AtDdFhE{D|IB`FtClAxEzBeCfAzAtC}Al@uDpC^zDoKrDFpAcH_@cEzCUiFCPtDyAfD_A{@lAEDiDqB{CbIiGm@wFnCeLhJ_CzGhApCuCzFh@`PgGn@pMtBrB`C~LnA~[zF`ChSqA|@vLzF|@|MqKpDuJvBgO~EmFxBE~R|KrLZrFcDrGkTnIiJfDW~QhHtNyB~BuG_@qVxBgHp^a\tRdHdAuBxO_CNmBeGcFi@eExKwDoAyNz@wE~PeHhBoH}CwDeCgNrD_RxEiGc@mXsCc^nDkPbGeBTsJyLsEyAiEeIsFaFo@K{GyBiHyL_BqIqPyJcDb@sJ_FoGqA_GmEcDjCLVgGe@}DmCsCWeEbGpHpFfRoA{CJ}EuSql@qBxFyC{GcDnDi@nHt@pHlC~EaD{CyB{J|@{OkCzNj@hH{Kf@oGhRmDrDvBtG?bLxC`CDlBqPkOsFmUiC~AkCkAgIdBeAoGtBxDnAk@tEwHxC{LjCeATsBe@s@]fCaCPgEvHb@wOiBgD{AJLqEiDwL`BeGe@cChAlAMtH|@kEo@cPqGz@uBoDuQMgHgC~@hKoK}K_Dn@qOmCiDLaCvEUdFeBLMmQeNeFiE?Lec@}HsLcCmGe@cHcUki@cLuRcb@mRgJmJ}GuNqJoC{AmDmEgByQlQsSwL_I|@gK_A{A_GfCeJs@yHkDiHgL{GwHsJeCsJsFlA{HkIaK{SmBuM}D{G}A{MiS_Ha`@o[oDpAmHfNqEnD_b@rMgVmJqS`UqNv@y@}CuNyLmIhXuOhB{Ar[_IfG|G|GWd\|AtNiAlCqIGqG`FsFOcKsHiPkRsDh@wAdFoCnA_ZoPwFDaHiHmRpB{TfTgJLyDeH}QwEg]bP{I|H_JgGqCpAuBcB}BhBaI}VcLqEwEtNiG|Hc@|FmFvHoIy@l@xEc[hOcFh@sKpI}JwAmXfF_Ur`@yCo@{EmM_R`CwCiDqByJ}LeCuRVc]hGke@}GiJfCyEi@sImG';
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Route Overlap</title>

  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

  <!-- Mapbox polyline decoder -->
  <script src="https://unpkg.com/@mapbox/polyline"></script>

  <style>
    body { margin:0; }
    #map { width:100%; height:100vh; }
    .stats {
      position:absolute;
      top:10px;
      left:10px;
      background:white;
      padding:10px 12px;
      border-radius:6px;
      font-family:sans-serif;
      box-shadow:0 2px 8px rgba(0,0,0,.2);
      z-index:1000;
    }
  </style>
</head>
<body>

<div id="map"></div>
<div class="stats" id="stats">Computing overlap…</div>

<script>
/* =======================
   INPUT
======================= */
const encoded1 = <?= json_encode($encoded1) ?>;
const encoded2 = <?= json_encode($encoded2) ?>;

/* =======================
   MAP SETUP
======================= */
const map = L.map('map');

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19
}).addTo(map);

/* =======================
   DECODE POLYLINES
======================= */
// Mapbox polyline.decode → [lat, lon]
const route1 = polyline.decode(encoded1);
const route2 = polyline.decode(encoded2);

// Convert to Leaflet LatLng
const latlngs1 = route1.map(p => L.latLng(p[0], p[1]));
const latlngs2 = route2.map(p => L.latLng(p[0], p[1]));

/* =======================
   DRAW ROUTES
======================= */
const line1 = L.polyline(latlngs1, { color:'blue', weight:4 }).addTo(map);
const line2 = L.polyline(latlngs2, { color:'red',  weight:4 }).addTo(map);

map.fitBounds(L.featureGroup([line1, line2]).getBounds(), { padding:[20,20] });

/* =======================
   GEOMETRY HELPERS (meters)
======================= */
function projectLine(latlngs) {
  return latlngs.map(ll => map.project(ll, map.getZoom()));
}

function pointToSegmentDistance(p, a, b) {
  const dx = b.x - a.x;
  const dy = b.y - a.y;

  if (dx === 0 && dy === 0) {
    return Math.hypot(p.x - a.x, p.y - a.y);
  }

  let t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / (dx*dx + dy*dy);
  t = Math.max(0, Math.min(1, t));

  const cx = a.x + t * dx;
  const cy = a.y + t * dy;

  return Math.hypot(p.x - cx, p.y - cy);
}

function segmentDistance(a1, a2, b1, b2) {
  return Math.min(
    pointToSegmentDistance(a1, b1, b2),
    pointToSegmentDistance(a2, b1, b2),
    pointToSegmentDistance(b1, a1, a2),
    pointToSegmentDistance(b2, a1, a2)
  );
}

/* =======================
   OVERLAP MATCHING
======================= */
function findOverlap(latlngsA, latlngsB, tolerance = 12, window = 25) {
  const pA = projectLine(latlngsA);
  const pB = projectLine(latlngsB);

  let total = 0;
  let overlap = 0;
  const segments = [];

  for (let i = 0; i < pA.length - 1; i++) {
    const a1 = pA[i];
    const a2 = pA[i+1];
    const segLen = Math.hypot(a2.x - a1.x, a2.y - a1.y);
    total += segLen;

    const start = Math.max(0, i - window);
    const end   = Math.min(pB.length - 2, i + window);

    let matched = false;

    for (let j = start; j <= end; j++) {
      if (segmentDistance(a1, a2, pB[j], pB[j+1]) <= tolerance) {
        matched = true;
        break;
      }
    }

    if (matched) {
      overlap += segLen;
      segments.push([latlngsA[i], latlngsA[i+1]]);
    }
  }

  return { total, overlap, percent: (overlap/total)*100, segments };
}

/* =======================
   RUN MATCH (both ways)
======================= */
const A = findOverlap(latlngs1, latlngs2);
const B = findOverlap(latlngs2, latlngs1);

// conservative result
const overlapMeters  = Math.min(A.overlap, B.overlap);
const overlapPercent = Math.min(A.percent, B.percent);

/* =======================
   DRAW OVERLAP
======================= */
A.segments.forEach(seg => {
  L.polyline(seg, { color:'lime', weight:7 }).addTo(map);
});

/* =======================
   UI STATS
======================= */
document.getElementById('stats').innerHTML = `
<b>Overlap</b><br>
Distance: ${(overlapMeters/1000).toFixed(2)} km<br>
Percent: ${overlapPercent.toFixed(1)} %
`;
</script>

</body>
</html>
