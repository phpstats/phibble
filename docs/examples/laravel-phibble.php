<?php

require __DIR__.'/vendor/autoload.php';
require '../examples/support/laravel.php';

// ---

use function Phibble\Expr\{col, mean, n};

$summary = Penguin::query()
    ->measured()
    ->where('year', '>=', 2008)
    ->phibble()
    ->groupBy(['species', 'sex'])
    ->summarize([
        'n' => n(),
        'mass' => mean(col('body_mass_g')),
    ]);

echo $summary->collect();
