<?php

$data = 'https://raw.githubusercontent.com/phpstats/datasets/main/csv';
$penguins = DataFrame::fromCsv(
    "{$data}/penguins.csv",
    types: '_ffddii_i',
);
echo $penguins->glimpse(), "\n";

$some = DataFrame::fromCsv(
    "{$data}/penguins.csv",
    types: ['species' => 'factor', '.default' => '_'],
    select: ['species'],
);
echo $some->glimpse();
