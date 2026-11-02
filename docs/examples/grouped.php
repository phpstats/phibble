<?php

$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
)
    ->dropNa(['body_mass_g']);

$centered = $penguins
    ->groupBy(['species'])
    ->mutate([
        'vs_species' => col('body_mass_g')
            ->sub(mean(col('body_mass_g'))),
    ])
    ->select(['species', 'body_mass_g', 'vs_species'])
    ->head(4);

echo $centered;
