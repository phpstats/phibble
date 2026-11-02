<?php

$rows = DataFrame::fromRows([
    ['species' => 'Adelie', 'island' => 'Torgersen', 'body_mass_g' => 3750],
    ['species' => 'Adelie', 'island' => 'Torgersen'],
]);
$cols = DataFrame::fromColumns(
    ['species' => ['Adelie', 'Gentoo'], 'island' => ['Torgersen', 'Biscoe']],
    types: ['species' => 'factor'],
);

echo $rows, "\n\n", $cols;
