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
$pdo->exec('CREATE TABLE airports (faa TEXT, name TEXT, alt INTEGER)');
$insert = $pdo->prepare('INSERT INTO airports VALUES (?, ?, ?)');
foreach (DataFrame::fromCsv("{$data}/airports.csv")->select(['faa', 'name', 'alt'])->toRows() as $row) {
    $insert->execute(array_values($row));
}

// ---

$weather = tbl($pdo, 'weather');
$airports = tbl($pdo, 'airports');

$joined = $weather
    ->groupBy(['origin'])
    ->summarize(['temp' => mean(col('temp'), na_rm: true)])
    ->join($airports, [['origin', 'faa']], 'left');

echo $joined->showQuery(), "\n\n";
echo $joined->arrange(['origin'])->collect();
