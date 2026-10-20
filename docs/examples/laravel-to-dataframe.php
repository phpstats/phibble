<?php

require '../examples/support/laravel.php';
// ---
$df = Penguin::query()
    ->where('year', 2009)
    ->get()
    ->toDataFrame();

echo $df->select(['species', 'island', 'body_mass'])->head(3);
