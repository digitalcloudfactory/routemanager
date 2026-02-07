<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$p1 = decodePolyline("kzehG`lrH~FpAhCbDxI~^rc@p_@~EG~B`IxLz@vAlGbE`F\lKj`@l\v[vd@~@vF~DItSrGtHSfElKxLpDb]th@`EpRjpA~nCrF|JxEpBOnGbb@p_A`HiEta@kDfEqBvE_MFoh@tK{QrP}Orj@t@dVmMfX|D`KeC~QwVbXiHvD{ChJy[pLaAnEqJtSuMlDaIv@kQlP[dF_CzI`@xI}DfDeJfEkEy@}Kd@mGbDyApNu`@lU}DrMyFjDdBdJwH`CtA~@wIlEqAbDzChNkJBoFbLkLqDcMuEcDaCFoAuClFoDzKg@oM_CnD}DrCkPlDkEpH~ErK?nMpIdUfb@vGxF~G}@lLyPpFiA`GpAdm@rc@bAzGdG|GnNh]h@zKvEjIOdMzCdElBvRRdNsEnRt@nd@nJxWzDdGxF`Cc@pBcDQ}DdDgC`FyAlQ|BlKuBrI}J|DiF`GbCfNfSzXfF|[i@bJiDrKt@hOgCtClDzXeChm@n@nJjCdI@|OaPfG{Fi@qCtC{GiAiJ~BoCdLl@vFcIhGpBzCEhDmAD~@z@xAgDQuDhFB{CT^bEqAbHsDG{DnKqC_@m@tDuC|AgA{A{BdCmAyEaFuC}ICiEzDuDeFoP|A_JuEmD`IuCuDkEvAgAcBmBl@b@uGzCiAVsBaEcEMkEwB{DyLdCy^bA{T?yC_E{ClI{JlCiDiAaEzB}Cq@mMjBkEiEnCwIe@cKbAqEiBrB[hE}A`@E~DaEtAmEtGq@jEaK`A{CyrAbAcKUsMyC{GmE_Bs@wB_@mFnBuJ?iH}XecCeHmGaBaGd@aKmBiHnAeJ}EySaAc[{CyOoKhAeKvKkCqAiKZE{JoCiHaRmJyQg\oOoQcBaIq@wh@gZkb@u@{XtEcWlBaGdHiHlHoYhPyY~Eel@dFwOuHSmHlFwEG}OcLkLcOsDh@wAdFoCnAmPsKqH{CsEXkHqH}DMiMrBuS`TmKRyDeH}P}Eg^hP_FhG{Cn@_IcGqCpAuBcB}BhBaI}VcLqEwEtNiG|Hc@|FmFvHoIy@l@xEc[hOcFh@sKpI}JwAmXfF{Ux`@}Bu@{EmM_R`CwCiDqByJ}LeCuRVc]hGke@}GiJfCmJ}AwHeJgGaC");
$p2 = decodePolyline("ezehG`lrHxFpAhCbDxI~^rc@p_@~EG~B`IxLz@vAlGbE`F\lKj`@l\v[vd@~@vF~DItSrGtHSfElKxLpDb]th@`EpRjpA~nCrF|JxEpBOnGn]|s@`I~WtKxFdHzU|DfC|@{Bx@j@tGnSs@vDlDnEhKZnNdLzDxFdD|OjIxTlRpTt@hDxQlJtElNtNdL|G|KxExNfBKzA_FvElF~NcAtv@gU|DrBjBmBdCLjJdFra@jEfJcAzJpA~IuGtDyL`KaAp@kElEuG`EuAD_E|Aa@ZiEhBsBcApEd@bKoCvIjEhElMkB|Cp@`E{BhDhAzJmCzCmIxC~DzT?x^cAxLeCvBzDLjE`EbEWrB{ChAc@tGlBm@fAbBjEwAtCtD`DcIjJvEnP}AtDdFhE{D|IB`FtClAxEzBeCfAzAtC}Al@uDpC^zDoKrDFpAcH_@cEzCUiFCPtDyAfD_A{@lAEDiDqB{CbIiGm@wFnCeLhJ_CzGhApCuCzFh@`PgGn@pMtBrB`C~LnA~[zF`ChSqA|@vLzF|@|MqKpDuJvBgO~EmFxBE~R|KrLZrFcDrGkTnIiJfDW~QhHtNyB~BuG_@qVxBgHp^a\tRdHdAuBxO_CNmBeGcFi@eExKwDoAyNz@wE~PeHhBoH}CwDeCgNrD_RxEiGc@mXsCc^nDkPbGeBTsJyLsEyAiEeIsFaFo@K{GyBiHyL_BqIqPyJcDb@sJ_FoGqA_GmEcDjCLVgGe@}DmCsCWeEbGpHpFfRoA{CJ}EuSql@qBxFyC{GcDnDi@nHt@pHlC~EaD{CyB{J|@{OkCzNj@hH{Kf@oGhRmDrDvBtG?bLxC`CDlBqPkOsFmUiC~AkCkAgIdBeAoGtBxDnAk@tEwHxC{LjCeATsBe@s@]fCaCPgEvHb@wOiBgD{AJLqEiDwL`BeGe@cChAlAMtH|@kEo@cPqGz@uBoDuQMgHgC~@hKoK}K_Dn@qOmCiDLaCvEUdFeBLMmQeNeFiE?Lec@}HsLcCmGe@cHcUki@cLuRcb@mRgJmJ}GuNqJoC{AmDmEgByQlQsSwL_I|@gK_A{A_GfCeJs@yHkDiHgL{GwHsJeCsJsFlA{HkIaK{SmBuM}D{G}A{MiS_Ha`@o[oDpAmHfNqEnD_b@rMgVmJqS`UqNv@y@}CuNyLmIhXuOhB{Ar[_IfG|G|GWd\|AtNiAlCqIGqG`FsFOcKsHiPkRsDh@wAdFoCnA_ZoPwFDaHiHmRpB{TfTgJLyDeH}QwEg]bP{I|H_JgGqCpAuBcB}BhBaI}VcLqEwEtNiG|Hc@|FmFvHoIy@l@xEc[hOcFh@sKpI}JwAmXfF_Ur`@yCo@{EmM_R`CwCiDqByJ}LeCuRVc]hGke@}GiJfCyEi@sImG");

//$p1 = decodePolyline($encoded1);
//$p2 = decodePolyline($encoded2);

$stats1 = overlapStatsSegment($p1, $p2, 20);
$stats2 = overlapStatsSegment($p2, $p1, 20);

$overlapMeters  = min($stats1['overlap_m'], $stats2['overlap_m']);
$overlapPercent = min($stats1['percent'],   $stats2['percent']);

echo "Overlap distance: " . round($overlapMeters) . " m\n";
echo "Overlap percent: "  . round($overlapPercent, 2) . " %\n";
echo "done v1.1";

function project($p, $lat0) {
    $R = 6371000;
    $x = deg2rad($p['lon']) * cos(deg2rad($lat0)) * $R;
    $y = deg2rad($p['lat']) * $R;
    return [$x, $y];
}

function pointToSegmentDistance($px, $py, $ax, $ay, $bx, $by) {
    $dx = $bx - $ax;
    $dy = $by - $ay;

    if ($dx == 0 && $dy == 0) {
        return hypot($px - $ax, $py - $ay);
    }

    $t = (($px - $ax) * $dx + ($py - $ay) * $dy) / ($dx*$dx + $dy*$dy);
    $t = max(0, min(1, $t));

    $cx = $ax + $t * $dx;
    $cy = $ay + $t * $dy;

    return hypot($px - $cx, $py - $cy);
}

function segmentDistance($a1, $a2, $b1, $b2) {
    return min(
        pointToSegmentDistance($a1[0], $a1[1], $b1[0], $b1[1], $b2[0], $b2[1]),
        pointToSegmentDistance($a2[0], $a2[1], $b1[0], $b1[1], $b2[0], $b2[1]),
        pointToSegmentDistance($b1[0], $b1[1], $a1[0], $a1[1], $a2[0], $a2[1]),
        pointToSegmentDistance($b2[0], $b2[1], $a1[0], $a1[1], $a2[0], $a2[1])
    );
}

function overlapStatsSegment($A, $B, $toleranceMeters) {
    $lat0 = $A[0]['lat']; // reference latitude

    // Project all points once
    $Ap = array_map(fn($p) => project($p, $lat0), $A);
    $Bp = array_map(fn($p) => project($p, $lat0), $B);

    $total = 0.0;
    $overlap = 0.0;

    $window = 20; // limits comparisons, tweak if needed

    for ($i = 0; $i < count($Ap) - 1; $i++) {
        $a1 = $Ap[$i];
        $a2 = $Ap[$i + 1];

        $segLen = hypot($a2[0] - $a1[0], $a2[1] - $a1[1]);
        $total += $segLen;

        $found = false;

        $start = max(0, $i - $window);
        $end   = min(count($Bp) - 2, $i + $window);



        if ($found) {
            $overlap += $segLen;
        }
    }

    return [
        'overlap_m' => $overlap,
        'total_m'   => $total,
        'percent'   => $total > 0 ? ($overlap / $total) * 100 : 0
    ];
}



function decodePolyline($encoded) {
    $points = [];
    $index = 0;
    $lat = 0;
    $lng = 0;
    $len = strlen($encoded);

    while ($index < $len) {
        $b = 0;
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

        $points[] = [
            'lat' => $lat * 1e-5,
            'lon' => $lng * 1e-5
        ];
    }

    return $points;
}
?>
