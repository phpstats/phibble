<?php

require __DIR__.'/vendor/autoload.php';

// ---

use Phibble\DataFrame;

use function Phibble\Expr\{col, is_na, sum};

$weather = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/weather.csv',
    types: ['origin' => 'factor'],
);

echo $weather->glimpse(), "\n";

echo implode(', ', $weather->levels('origin')), "\n\n";

echo $weather->summarize([
    'temp' => sum(is_na(col('temp'))),
    'wind_gust' => sum(is_na(col('wind_gust'))),
    'pressure' => sum(is_na(col('pressure'))),
]), "\n";
