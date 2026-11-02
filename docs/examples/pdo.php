<?php

$data = 'https://raw.githubusercontent.com/phpstats/datasets/main/csv';
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE airports (faa TEXT, name TEXT, alt INTEGER, tzone TEXT)');
$insert = $pdo->prepare('INSERT INTO airports VALUES (?, ?, ?, ?)');
foreach (DataFrame::fromCsv("{$data}/airports.csv")->select(['faa', 'name', 'alt', 'tzone'])->toRows() as $row) {
    $insert->execute(array_values($row));
}

// ---

$high = DataFrame::fromPdo(
    $pdo,
    'SELECT * FROM airports WHERE tzone = ? AND alt > ? ORDER BY alt DESC',
    ['America/New_York', 2000],
);

echo $high;
