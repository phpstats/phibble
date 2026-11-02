<?php

require __DIR__.'/vendor/autoload.php';
require '../examples/support/laravel.php';

// ---

$df = Penguin::query()
    ->where('year', 2009)
    ->get()
    ->toDataFrame();

echo $df->select(['species', 'island', 'body_mass_g'])->head(3);
