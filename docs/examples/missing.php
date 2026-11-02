<?php

$penguins = DataFrame::fromCsv(
    'https://raw.githubusercontent.com/phpstats/datasets/'
        .'main/csv/penguins.csv'
)->head(4)->select(['species', 'body_mass_g']);

echo $penguins->mutate([
    'mass_kg' => col('body_mass_g')->div(1000),
]), "\n";
echo $penguins->summarize([
    'mean' => mean(col('body_mass_g')),
    'mean_known' => mean(col('body_mass_g'), na_rm: true),
]);
