<?php

$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
)->asFactor('species');

echo json_encode($penguins->levels('species')), "\n";
$codes = $penguins->storage('species')->codes();
echo json_encode(array_slice($codes, 150, 5)), "\n";

$sizes = tibble(size: ['small', 'large', 'medium', 'small'])
    ->asOrdered('size', ['small', 'medium', 'large']);

echo $sizes->arrange(['size']), "\n";
var_dump($sizes->isOrdered('size'));
