<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/geophp/geoPHP.inc';

echo 'geoPHP loaded<br>';

$lineA = geoPHP::load(
    'LINESTRING(0 0, 10 0, 20 0)',
    'wkt'
);

$lineB = geoPHP::load(
    'LINESTRING(5 0, 15 0)',
    'wkt'
);
echo($lineA);
var_dump($lineA);

$intersection = $lineA->intersection($lineB);

if ($intersection && !$intersection->isEmpty()) {
    echo $intersection->length();
} else {
    echo 0;
}
?>
