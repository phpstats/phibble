<?php

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE penguins (
    species TEXT, island TEXT, bill_length_mm REAL, bill_depth_mm REAL,
    flipper_length_mm INTEGER, body_mass_g INTEGER, sex TEXT, year INTEGER
)');
$insert = $pdo->prepare(
    'INSERT INTO penguins VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
);
$csv = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/main/csv/penguins.csv'
);
foreach ($csv->select(['species', 'island', 'bill_length_mm', 'bill_depth_mm', 'flipper_length_mm', 'body_mass_g', 'sex', 'year'])->toRows() as $row) {
    $insert->execute(array_values($row));
}

// ---

$penguins = tbl($pdo, 'penguins');

$summary = $penguins
    ->filter(col('year')->eq(2008))
    ->groupBy(['species'])
    ->summarize([
        'n' => n(),
        'mass' => mean(col('body_mass_g'), na_rm: true),
    ]);

echo get_class($summary), "\n\n";
echo $summary->showQuery(), "\n\n";
echo json_encode($summary->bindings()), "\n\n";
echo $summary->collect();
