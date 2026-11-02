<?php

require __DIR__.'/vendor/autoload.php';

// ---

use Phibble\DataFrame;

use function Phibble\Expr\{col, mean, median, n};

$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
);

$summary = $penguins
    ->filter(col('body_mass_g')->isNa()->not())
    ->groupBy(['species', 'sex'])
    ->summarize([
        'n' => n(),
        'mass' => mean(col('body_mass_g')),
        'flipper' => median(col('flipper_length_mm')),
    ]);

echo $summary, "\n";
