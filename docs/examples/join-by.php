<?php

$data = 'https://raw.githubusercontent.com/phpstats/datasets/main/csv';
$flights = DataFrame::fromCsv("{$data}/flights.csv", nMax: 1000)
    ->filter(col('origin')->eq('EWR')->and(col('time_hour')->between(
        ymd_hms('2013-01-01 16:00:00'),
        ymd_hms('2013-01-01 18:00:00'),
    )))
    ->distinct(['origin', 'time_hour']);
$weather = DataFrame::fromCsv("{$data}/weather.csv")
    ->select(['origin', 'time_hour', 'temp', 'wind_speed'])
    ->rename(['observed' => 'time_hour']);

echo $flights->join(
    $weather,
    ['origin', ['closest', 'time_hour', '>=', 'observed']],
    'left',
);
