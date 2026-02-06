<?php
require_once __DIR__ . '/vendor/geophp/geoPHP.inc';

$lineA = geoPHP::load(
    'LINESTRING(0 0, 10 0, 20 0)',
    'wkt'
);

$lineB = geoPHP::load(
    'LINESTRING(5 0, 15 0)',
    'wkt'
);

$intersection = $lineA->intersection($lineB);

if ($intersection && !$intersection->isEmpty()) {
    echo $intersection->length();
} else {
    echo 0;
}
?>
