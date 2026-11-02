<?php

$data = 'https://raw.githubusercontent.com/phpstats/datasets/main/csv';
$weather = DataFrame::fromCsv("{$data}/weather.csv");
$airports = DataFrame::fromCsv("{$data}/airports.csv");

$stations = $weather
    ->distinct(['origin'])
    ->join($airports, [['origin', 'faa']], 'left')
    ->select(['origin', 'name', 'lat', 'lon', 'alt']);

echo $stations;
