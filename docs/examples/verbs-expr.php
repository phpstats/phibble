<?php

$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
);

$heavy = $penguins
    ->select(['species', 'island', 'body_mass_g', 'year'])
    ->filter(col('body_mass_g')->gt(5500))
    ->mutate(['kg' => col('body_mass_g')->div(1000)])
    ->arrange([desc(col('body_mass_g'))])
    ->head(5);

echo $heavy;
