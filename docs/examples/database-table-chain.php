<?php

require __DIR__.'/vendor/autoload.php';

use Phibble\DataFrame;

$weather = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/main/csv/weather.csv'
);
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE weather (
    origin TEXT, month INTEGER, temp REAL, precip REAL, wind_gust REAL
)');
$insert = $pdo->prepare('INSERT INTO weather VALUES (?, ?, ?, ?, ?)');
$pdo->beginTransaction();
foreach ($weather->select(['origin', 'month', 'temp', 'precip', 'wind_gust'])->toRows() as $row) {
    $insert->execute(array_values($row));
}
$pdo->commit();

// ---

use function Phibble\tbl;
use function Phibble\Expr\{col, desc, max, mean, n, sum};

$weather = tbl($pdo, 'weather');

$airports = $weather
    ->filter(col('temp')->isNa()->not())
    ->groupBy(['origin'])
    ->summarize([
        'hours' => n(),
        'temp' => mean(col('temp')),
        'precip' => sum(col('precip')),
        'gust' => max(col('wind_gust'), na_rm: true),
    ])
    ->arrange([desc(col('precip'))]);

echo $airports->showQuery(), "\n\n";
echo $airports->collect(), "\n";
