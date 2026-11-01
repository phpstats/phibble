<?php

require __DIR__.'/vendor/autoload.php';

// ---

use Phibble\DataFrame;

use function Phibble\Expr\{col, mean, median, n};

$penguins = DataFrame::fromCsv('penguins.csv');

$summary = $penguins
    ->filter(col('body_mass')->isNa()->not())
    ->groupBy(['species', 'sex'])
    ->summarize([
        'n' => n(),
        'mass' => mean(col('body_mass')),
        'flipper' => median(col('flipper_len')),
    ]);

echo $summary, "\n";
