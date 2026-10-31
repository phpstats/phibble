<?php

require __DIR__.'/vendor/autoload.php';

use Phibble\DataFrame;

$readings = DataFrame::fromCsv('readings.csv', types: ['station' => 'factor']);

echo $readings, "\n";
