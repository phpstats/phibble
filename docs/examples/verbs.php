<?php

$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
);

$heavy = $penguins
    ->select(['species', 'island', 'body_mass_g', 'year'])
    ->filter(fn ($r) => $r->body_mass_g > 5500)
    ->mutate(['kg' => fn ($r) => $r->body_mass_g / 1000])
    ->arrange([['body_mass_g', 'desc']])
    ->head(5);

echo $heavy;
