<?php

$data = 'https://raw.githubusercontent.com/phpstats/datasets/main/csv';
$monthly = DataFrame::fromCsv("{$data}/weather.csv")
    ->filter(col('origin')->eq('JFK'))
    ->groupBy(['month'])
    ->summarize(['precip' => sum(col('precip'))]);

echo $monthly->mutate([
    'prev' => lag(col('precip')),
    'change' => col('precip')->sub(lag(col('precip'))),
    'running' => cumsum(col('precip')),
    'rank' => min_rank(desc(col('precip'))),
    'half' => ntile(col('precip'), 2),
]);
