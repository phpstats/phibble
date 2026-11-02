<?php

require __DIR__.'/vendor/autoload.php';
require '../examples/support/laravel.php';

// ---

use function Phibble\Expr\{col, max};

$heaviest = Penguin::query()
    ->measured()
    ->phibble()
    ->groupBy(['island'])
    ->filter(col('body_mass_g')->eq(max(col('body_mass_g'))))
    ->select(['island', 'species', 'body_mass_g']);

echo $heaviest->showQuery(), "\n\n";
echo $heaviest->arrange(['island'])->collect();
