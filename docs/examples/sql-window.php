<?php

$data = 'https://raw.githubusercontent.com/phpstats/datasets/main/csv';
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE weather (
    origin TEXT, month INTEGER, day INTEGER, temp REAL, precip REAL
)');
$insert = $pdo->prepare('INSERT INTO weather VALUES (?, ?, ?, ?, ?)');
$pdo->beginTransaction();
foreach (DataFrame::fromCsv("{$data}/weather.csv")->select(['origin', 'month', 'day', 'temp', 'precip'])->toRows() as $row) {
    $insert->execute(array_values($row));
}
$pdo->commit();

// ---

$weather = tbl($pdo, 'weather');

$out = $weather
    ->filter(col('month')->between(6, 8))
    ->groupBy(['origin', 'month'])
    ->summarize(['precip' => sum(col('precip'))])
    ->groupBy(['origin'])
    ->arrange(['origin', 'month'])
    ->mutate([
        'running' => cumsum(col('precip')),
        'of_wettest' => col('precip')
            ->div(max(col('precip'), na_rm: true)),
    ]);

echo $out->showQuery(), "\n\n";
echo $out->collect();
