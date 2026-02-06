<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/geophp/geoPHP.inc';

echo 'geoPHP loaded<br>';

$lineA = decodePolyline("kzehG`lrH~FpAhCbDxI~^rc@p_@~EG~B`IxLz@vAlGbE`F\lKj`@l\v[vd@~@vF~DItSrGtHSfElKxLpDb]th@`EpRjpA~nCrF|JxEpBOnGbb@p_A`HiEta@kDfEqBvE_MFoh@tK{QrP}Orj@t@dVmMfX|D`KeC~QwVbXiHvD{ChJy[pLaAnEqJtSuMlDaIv@kQlP[dF_CzI`@xI}DfDeJfEkEy@}Kd@mGbDyApNu`@lU}DrMyFjDdBdJwH`CtA~@wIlEqAbDzChNkJBoFbLkLqDcMuEcDaCFoAuClFoDzKg@oM_CnD}DrCkPlDkEpH~ErK?nMpIdUfb@vGxF~G}@lLyPpFiA`GpAdm@rc@bAzGdG|GnNh]h@zKvEjIOdMzCdElBvRRdNsEnRt@nd@nJxWzDdGxF`Cc@pBcDQ}DdDgC`FyAlQ|BlKuBrI}J|DiF`GbCfNfSzXfF|[i@bJiDrKt@hOgCtClDzXeChm@n@nJjCdI@|OaPfG{Fi@qCtC{GiAiJ~BoCdLl@vFcIhGpBzCEhDmAD~@z@xAgDQuDhFB{CT^bEqAbHsDG{DnKqC_@m@tDuC|AgA{A{BdCmAyEaFuC}ICiEzDuDeFoP|A_JuEmD`IuCuDkEvAgAcBmBl@b@uGzCiAVsBaEcEMkEwB{DyLdCy^bA{T?yC_E{ClI{JlCiDiAaEzB}Cq@mMjBkEiEnCwIe@cKbAqEiBrB[hE}A`@E~DaEtAmEtGq@jEaK`A{CyrAbAcKUsMyC{GmE_Bs@wB_@mFnBuJ?iH}XecCeHmGaBaGd@aKmBiHnAeJ}EySaAc[{CyOoKhAeKvKkCqAiKZE{JoCiHaRmJyQg\oOoQcBaIq@wh@gZkb@u@{XtEcWlBaGdHiHlHoYhPyY~Eel@dFwOuHSmHlFwEG}OcLkLcOsDh@wAdFoCnAmPsKqH{CsEXkHqH}DMiMrBuS`TmKRyDeH}P}Eg^hP_FhG{Cn@_IcGqCpAuBcB}BhBaI}VcLqEwEtNiG|Hc@|FmFvHoIy@l@xEc[hOcFh@sKpI}JwAmXfF{Ux`@}Bu@{EmM_R`CwCiDqByJ}LeCuRVc]hGke@}GiJfCmJ}AwHeJgGaC");
$lineB = decodePolyline("ezehG`lrHxFpAhCbDxI~^rc@p_@~EG~B`IxLz@vAlGbE`F\lKj`@l\v[vd@~@vF~DItSrGtHSfElKxLpDb]th@`EpRjpA~nCrF|JxEpBOnGn]|s@`I~WtKxFdHzU|DfC|@{Bx@j@tGnSs@vDlDnEhKZnNdLzDxFdD|OjIxTlRpTt@hDxQlJtElNtNdL|G|KxExNfBKzA_FvElF~NcAtv@gU|DrBjBmBdCLjJdFra@jEfJcAzJpA~IuGtDyL`KaAp@kElEuG`EuAD_E|Aa@ZiEhBsBcApEd@bKoCvIjEhElMkB|Cp@`E{BhDhAzJmCzCmIxC~DzT?x^cAxLeCvBzDLjE`EbEWrB{ChAc@tGlBm@fAbBjEwAtCtD`DcIjJvEnP}AtDdFhE{D|IB`FtClAxEzBeCfAzAtC}Al@uDpC^zDoKrDFpAcH_@cEzCUiFCPtDyAfD_A{@lAEDiDqB{CbIiGm@wFnCeLhJ_CzGhApCuCzFh@`PgGn@pMtBrB`C~LnA~[zF`ChSqA|@vLzF|@|MqKpDuJvBgO~EmFxBE~R|KrLZrFcDrGkTnIiJfDW~QhHtNyB~BuG_@qVxBgHp^a\tRdHdAuBxO_CNmBeGcFi@eExKwDoAyNz@wE~PeHhBoH}CwDeCgNrD_RxEiGc@mXsCc^nDkPbGeBTsJyLsEyAiEeIsFaFo@K{GyBiHyL_BqIqPyJcDb@sJ_FoGqA_GmEcDjCLVgGe@}DmCsCWeEbGpHpFfRoA{CJ}EuSql@qBxFyC{GcDnDi@nHt@pHlC~EaD{CyB{J|@{OkCzNj@hH{Kf@oGhRmDrDvBtG?bLxC`CDlBqPkOsFmUiC~AkCkAgIdBeAoGtBxDnAk@tEwHxC{LjCeATsBe@s@]fCaCPgEvHb@wOiBgD{AJLqEiDwL`BeGe@cChAlAMtH|@kEo@cPqGz@uBoDuQMgHgC~@hKoK}K_Dn@qOmCiDLaCvEUdFeBLMmQeNeFiE?Lec@}HsLcCmGe@cHcUki@cLuRcb@mRgJmJ}GuNqJoC{AmDmEgByQlQsSwL_I|@gK_A{A_GfCeJs@yHkDiHgL{GwHsJeCsJsFlA{HkIaK{SmBuM}D{G}A{MiS_Ha`@o[oDpAmHfNqEnD_b@rMgVmJqS`UqNv@y@}CuNyLmIhXuOhB{Ar[_IfG|G|GWd\|AtNiAlCqIGqG`FsFOcKsHiPkRsDh@wAdFoCnA_ZoPwFDaHiHmRpB{TfTgJLyDeH}QwEg]bP{I|H_JgGqCpAuBcB}BhBaI}VcLqEwEtNiG|Hc@|FmFvHoIy@l@xEc[hOcFh@sKpI}JwAmXfF_Ur`@yCo@{EmM_R`CwCiDqByJ}LeCuRVc]hGke@}GiJfCyEi@sImG");

$lineADistance = 68.6606;
//$lineA = geoPHP::load('LINESTRING(0 0, 10 0)', 'wkt');
//$lineB = geoPHP::load('LINESTRING(5 0, 15 0)', 'wkt');

$overlap = polylineOverlapLength($lineA, $lineB);
//echo $overlap;

$lat = $lineA[0][1]; // average latitude is fine
$overlapMeters = $overlap * metersPerDegreeLng($lat);

$lineAmeters = $lineADistance * 1000;
$percent = ($overlapMeters / $lineAmeters) * 100;

echo $overlapMeters;
echo $percent; echo "%";

    
function decodePolyline(string $encoded): array {
    $points = [];
    $index = 0;
    $lat = 0;
    $lng = 0;
    $len = strlen($encoded);

    while ($index < $len) {
        $shift = 0;
        $result = 0;

        do {
            $b = ord($encoded[$index++]) - 63;
            $result |= ($b & 0x1f) << $shift;
            $shift += 5;
        } while ($b >= 0x20);

        $dlat = ($result & 1) ? ~($result >> 1) : ($result >> 1);
        $lat += $dlat;

        $shift = 0;
        $result = 0;

        do {
            $b = ord($encoded[$index++]) - 63;
            $result |= ($b & 0x1f) << $shift;
            $shift += 5;
        } while ($b >= 0x20);

        $dlng = ($result & 1) ? ~($result >> 1) : ($result >> 1);
        $lng += $dlng;

        // NOTE: Google uses lat,lng — we store as [lng, lat]
        $points[] = [$lng * 1e-5, $lat * 1e-5];
    }

    return $points;
}

function segmentLength($a, $b) {
    return hypot($b[0] - $a[0], $b[1] - $a[1]);
}

function collinear($a, $b, $c, $tol = 1e-9) {
    return abs(
        ($b[0] - $a[0]) * ($c[1] - $a[1]) -
        ($b[1] - $a[1]) * ($c[0] - $a[0])
    ) < $tol;
}

function overlap1D($a1, $a2, $b1, $b2) {
    return max(0, min($a2, $b2) - max($a1, $b1));
}

function segmentOverlap($a1, $a2, $b1, $b2) {
    if (!collinear($a1, $a2, $b1) || !collinear($a1, $a2, $b2)) {
        return 0;
    }

    // project on dominant axis
    if (abs($a2[0] - $a1[0]) >= abs($a2[1] - $a1[1])) {
        return overlap1D(
            min($a1[0], $a2[0]), max($a1[0], $a2[0]),
            min($b1[0], $b2[0]), max($b1[0], $b2[0])
        );
    } else {
        return overlap1D(
            min($a1[1], $a2[1]), max($a1[1], $a2[1]),
            min($b1[1], $b2[1]), max($b1[1], $b2[1])
        );
    }
}

function polylineOverlapLength($lineA, $lineB) {
    $len = 0;

    for ($i = 0; $i < count($lineA) - 1; $i++) {
        for ($j = 0; $j < count($lineB) - 1; $j++) {
            $len += segmentOverlap(
                $lineA[$i], $lineA[$i+1],
                $lineB[$j], $lineB[$j+1]
            );
        }
    }
    return $len;
}
function metersPerDegreeLat($lat) {
    return 111320;
}

function metersPerDegreeLng($lat) {
    return 111320 * cos(deg2rad($lat));
}

?>
